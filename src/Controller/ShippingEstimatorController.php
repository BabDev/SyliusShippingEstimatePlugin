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
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class ShippingEstimatorController extends AbstractController
{
    private MetadataInterface $metadata;

    private RequestConfigurationFactoryInterface $requestConfigurationFactory;

    private CartContextInterface $cartContext;

    private ViewHandlerInterface $viewHandler;

    private AddressFactoryInterface $addressFactory;

    private AdjustmentFactoryInterface $adjustmentFactory;

    private ShippingMethodsResolverInterface $shippingMethodsResolver;

    private ServiceRegistryInterface $shippingCalculatorRegistry;

    private MoneyFormatterInterface $moneyFormatter;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        CartContextInterface $cartContext,
        ViewHandlerInterface $viewHandler,
        AddressFactoryInterface $addressFactory,
        AdjustmentFactoryInterface $adjustmentFactory,
        ShippingMethodsResolverInterface $shippingMethodsResolver,
        ServiceRegistryInterface $shippingCalculatorRegistry,
        MoneyFormatterInterface $moneyFormatter,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->cartContext = $cartContext;
        $this->viewHandler = $viewHandler;
        $this->addressFactory = $addressFactory;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->shippingMethodsResolver = $shippingMethodsResolver;
        $this->shippingCalculatorRegistry = $shippingCalculatorRegistry;
        $this->moneyFormatter = $moneyFormatter;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @psalm-suppress MixedArgument
     * @psalm-suppress PossiblyNullArgument
     */
    public function estimateShipping(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $form = $this->createEstimatorForm($configuration);

        $form->handleRequest($request);

        if (!$form->isValid()) {
            return $this->viewHandler->handle($configuration, View::create($form, Response::HTTP_BAD_REQUEST));
        }

        /** @var OrderInterface $cart */
        $cart = $this->cartContext->getCart();

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setCountryCode($form->get('country')->getData());
        $address->setPostcode($form->get('postcode')->getData());

        $event = new BeforeEstimateShippingEvent($cart, $address);

        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_estimate_cancelled', 'custom_reason' => $event->getCancelReason()], 400);
        }

        $address = $event->getAddress();

        $cart->setShippingAddress($address);

        $shipments = $cart->getShipments();

        if ($shipments->count() === 0) {
            throw new HttpException(500, 'A shipment was not created for this order.');
        }

        /** @var ShipmentInterface $shipment */
        $shipment = $shipments->first();
        $shipment->setOrder($cart);

        if (!$this->shippingMethodsResolver->supports($shipment)) {
            return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_not_supported'], 400);
        }

        $shippingOptions = [];
        $hadError = false;

        /** @var ShippingMethodInterface $shippingMethod */
        foreach ($this->shippingMethodsResolver->getSupportedMethods($shipment) as $shippingMethod) {
            try {
                /** @var CalculatorInterface $calculator */
                $calculator = $this->shippingCalculatorRegistry->get($shippingMethod->getCalculator());

                /** @var AdjustmentInterface $adjustment */
                $adjustment = $this->adjustmentFactory->createWithData(
                    AdjustmentInterface::SHIPPING_ADJUSTMENT,
                    $shippingMethod->getName(),
                    $calculator->calculate($shipment, $shippingMethod->getConfiguration())
                );

                $shippingOptions[] = [
                    'name' => $shippingMethod->getName(),
                    'rate' => $this->moneyFormatter->format($adjustment->getAmount(), $cart->getCurrencyCode()),
                ];
            } catch (\Exception $exception) {
                // Errored out getting a rate for this calculator, just skip it; we can show the calculator error message if the options list is totally empty
                $hadError = true;
            }
        }

        if ($shippingOptions === []) {
            if ($hadError) {
                return new JsonResponse(['error' => true, 'options' => [], 'reason' => 'shipping_calculator_error'], 500);
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

        /** @psalm-suppress MixedArgument */
        return $this->render($configuration->getTemplate('_widget.html'), [
            'cart' => $cart,
            'form' => $form->createView(),
        ]);
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

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $this->get('form.factory');

        if ($configuration->isHtmlRequest()) {
            return $formFactory->create($formType, null, $formOptions);
        }

        return $formFactory->createNamed('', $formType, null, array_merge($formOptions, ['csrf_protection' => false]));
    }
}
