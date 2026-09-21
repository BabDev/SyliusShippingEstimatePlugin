<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Controller;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimate;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimatorInterface;
use BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Twig\Environment;

final class ShippingEstimatorController
{
    public function __construct(
        private MetadataInterface $metadata,
        private RequestConfigurationFactoryInterface $requestConfigurationFactory,
        private CartContextInterface $cartContext,
        private FormFactoryInterface $formFactory,
        private Environment $twig,
        private AddressFactoryInterface $addressFactory,
        private ShippingEstimatorInterface $estimator,
        private ShippingEstimateResponderInterface $responder,
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
            return $this->responder->respond(
                ShippingEstimate::unavailable(ShippingEstimateReasons::INVALID_REQUEST),
                $request,
            );
        }

        /** @var OrderInterface $cart */
        $cart = $this->cartContext->getCart();

        return $this->responder->respond(
            $this->estimator->estimate($cart, $this->createEstimateAddress($form)),
            $request,
        );
    }

    /**
     * Builds the address to estimate for out of what the customer typed.
     */
    private function createEstimateAddress(FormInterface $form): AddressInterface
    {
        /** @var string|null $countryCode */
        $countryCode = $form->get('country')->getData();

        /** @var string|null $postcode */
        $postcode = $form->get('postcode')->getData();

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setCountryCode($countryCode);
        $address->setPostcode($postcode);

        return $address;
    }

    public function renderWidget(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $form = $this->createEstimatorForm($configuration);

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
     *
     * This answer does not go through the responder: being refused is not an estimate, and there is
     * no estimate to hand one.
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
