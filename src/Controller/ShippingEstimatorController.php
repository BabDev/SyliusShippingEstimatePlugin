<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Controller;

use BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent;
use FOS\RestBundle\View\View;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

final class ShippingEstimatorController
{
    public function __construct(
        private MetadataInterface $metadata,
        private RequestConfigurationFactoryInterface $requestConfigurationFactory,
        private CartContextInterface $cartContext,
        private ViewHandlerInterface $viewHandler,
        private FormFactoryInterface $formFactory,
        private Environment $twig,
        private AddressFactoryInterface $addressFactory,
        private ShippingMethodsResolverInterface $shippingMethodsResolver,
        private DelegatingCalculatorInterface $shippingCalculator,
        private MoneyFormatterInterface $moneyFormatter,
        private EventDispatcherInterface $eventDispatcher,
        private ?RateLimiterFactory $rateLimiterFactory = null,
    ) {
    }

    public function estimateShipping(Request $request): Response
    {
        $response = $this->enforceRateLimit($request) ?? $this->doEstimateShipping($request);

        /*
         * An estimate is specific to one customer's cart and the address they typed, and it is
         * served over GET, so make sure nothing between the shop and the browser keeps a copy of
         * one customer's rates to hand to the next.
         */
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    private function doEstimateShipping(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $form = $this->createEstimatorForm($configuration);

        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->viewHandler->handle($configuration, View::create($form, Response::HTTP_BAD_REQUEST));
        }

        /** @var OrderInterface $cart */
        $cart = $this->cartContext->getCart();

        /** @var string|null $countryCode */
        $countryCode = $form->get('country')->getData();

        /** @var string|null $postcode */
        $postcode = $form->get('postcode')->getData();

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setCountryCode($countryCode);
        $address->setPostcode($postcode);

        $event = new BeforeEstimateShippingEvent($cart, $address);

        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_estimate_cancelled', 'custom_reason' => $event->getCancelReason()], Response::HTTP_BAD_REQUEST);
        }

        $address = $event->getAddress();

        $originalShippingAddress = $cart->getShippingAddress();

        // The shipping method resolver reads the address from the shipment's order, so the estimate address has to be put on the cart.
        $cart->setShippingAddress($address);

        try {
            return $this->buildEstimate($cart);
        } finally {
            $cart->setShippingAddress($originalShippingAddress);
        }
    }

    /**
     * Resolves the shipping methods available to the cart and prices each of them.
     *
     * Expects the address being estimated for to already be set on the cart.
     */
    private function buildEstimate(OrderInterface $cart): Response
    {
        $shipments = $cart->getShipments();

        if ($shipments->count() === 0) {
            return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_not_available']);
        }

        /** @var ShipmentInterface $shipment */
        $shipment = $shipments->first();
        $shipment->setOrder($cart);

        if (!$this->shippingMethodsResolver->supports($shipment)) {
            return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_not_supported']);
        }

        $shippingOptions = [];
        $hadError = false;

        $originalShippingMethod = $shipment->getMethod();

        try {
            /** @var ShippingMethodInterface $shippingMethod */
            foreach ($this->shippingMethodsResolver->getSupportedMethods($shipment) as $shippingMethod) {
                try {
                    $shipment->setMethod($shippingMethod);

                    $shippingOptions[] = [
                        'name' => $shippingMethod->getName(),
                        'rate' => $this->moneyFormatter->format(
                            $this->shippingCalculator->calculate($shipment),
                            (string) $cart->getCurrencyCode(),
                        ),
                    ];
                } catch (\Exception) {
                    // Errored out getting a rate for this calculator, just skip it; we can show the calculator error message if the options list is totally empty
                    $hadError = true;
                }
            }
        } finally {
            $shipment->setMethod($originalShippingMethod);
        }

        if ($shippingOptions === []) {
            if ($hadError) {
                return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_calculator_error'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_not_available']);
        }

        return new JsonResponse(['error' => false, 'options' => $shippingOptions, 'reason' => null]);
    }

    public function renderWidget(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $form = $this->createEstimatorForm($configuration);

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create($form));
        }

        $cart = $this->cartContext->getCart();

        /** @var string $template */
        $template = $configuration->getTemplate('_widget.html');

        return new Response($this->twig->render($template, [
            'cart' => $cart,
            'form' => $form->createView(),
        ]));
    }

    /**
     * Applies the configured rate limit to the requesting client.
     *
     * Returns the response to send when the client has no requests left, or null to carry on. The
     * endpoint is unauthenticated and a shipping calculator may call an external carrier API on
     * every request, so the limit is keyed on the client address.
     */
    private function enforceRateLimit(Request $request): ?Response
    {
        if (null === $this->rateLimiterFactory) {
            return null;
        }

        $limit = $this->rateLimiterFactory->create($request->getClientIp())->consume();

        if ($limit->isAccepted()) {
            return null;
        }

        $response = new JsonResponse(
            ['error' => true, 'options' => [], 'reason' => 'shipping_estimate_rate_limited'],
            Response::HTTP_TOO_MANY_REQUESTS,
        );

        $response->headers->set('Retry-After', (string) ($limit->getRetryAfter()->getTimestamp() - time()));
        $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
        $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());

        return $response;
    }

    /**
     * Creates the estimator form type.
     *
     * This is a version of `Sylius\Bundle\ResourceBundle\Controller\ResourceFormFactoryInterface::create()`
     * which does not require a `Sylius\Component\Resource\Model\ResourceInterface` to inject into the form.
     */
    private function createEstimatorForm(RequestConfiguration $configuration): FormInterface
    {
        $formType = (string) $configuration->getFormType();
        $formOptions = $configuration->getFormOptions();

        if ($configuration->isHtmlRequest()) {
            return $this->formFactory->create($formType, null, $formOptions);
        }

        /*
         * The estimate is served from a GET route, so the form must be configured to match; a form
         * left at the default POST method is never submitted by `handleRequest()` on a GET request.
         */
        return $this->formFactory->createNamed('', $formType, null, array_merge($formOptions, [
            'csrf_protection' => false,
            'method' => Request::METHOD_GET,
        ]));
    }
}
