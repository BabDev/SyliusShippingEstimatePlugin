<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Functional\Controller;

use BabDev\SyliusShippingEstimatePlugin\Controller\ShippingEstimatorController;
use BabDev\SyliusShippingEstimatePlugin\Estimator\EventDispatchingShippingEstimator;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimator;
use BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent;
use BabDev\SyliusShippingEstimatePlugin\Form\Type\ShippingEstimatorType;
use BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponder;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AddressingBundle\Form\Type\CountryChoiceType;
use Sylius\Bundle\AddressingBundle\Form\Type\CountryCodeChoiceType;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Bundle\ResourceBundle\Controller\Parameters;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Addressing\Model\Country;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Shipment;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

final class ShippingEstimatorControllerTest extends TestCase
{
    /**
     * @test
     */
    public function it_handles_a_get_request_without_erroring_on_an_unsubmitted_form(): void
    {
        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new JsonResponse(['error' => true], Response::HTTP_BAD_REQUEST))
        ;

        $controller = $this->createController($viewHandler, new EventDispatcher());

        // No query data, so the form submits empty and fails validation rather than never submitting at all.
        $response = $controller->estimateShipping($this->createEstimateRequest([]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_binds_the_country_and_postcode_from_the_query_string(): void
    {
        $dispatchedEvent = null;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            BeforeEstimateShippingEvent::class,
            static function (BeforeEstimateShippingEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;

                // Cancelling short-circuits the controller before it reaches the cart's shipments,
                // keeping this test on the request handling rather than the rate calculation.
                $event->cancelEstimate('Stopping here.');
            },
        );

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler->expects(self::never())->method('handle');

        $controller = $this->createController($viewHandler, $eventDispatcher);

        $response = $controller->estimateShipping(
            $this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']),
        );

        self::assertInstanceOf(BeforeEstimateShippingEvent::class, $dispatchedEvent);
        self::assertSame('US', $dispatchedEvent->getAddress()->getCountryCode());
        self::assertSame('90802', $dispatchedEvent->getAddress()->getPostcode());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => true,
                'options' => [],
                'reason' => 'shipping_estimate_cancelled',
                'custom_reason' => 'Stopping here.',
            ], \JSON_THROW_ON_ERROR),
            (string) $response->getContent(),
        );
    }

    /**
     * @test
     */
    public function it_reports_shipping_as_unavailable_for_a_cart_without_a_shipment(): void
    {
        /** @var Stub&OrderInterface $cart */
        $cart = $this->createStub(OrderInterface::class);
        $cart->method('getShipments')->willReturn(new ArrayCollection());

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler->expects(self::never())->method('handle');

        $response = $this->createController($viewHandler, new EventDispatcher(), $cart)->estimateShipping(
            $this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => true,
                'options' => [],
                'reason' => 'shipping_not_available',
            ], \JSON_THROW_ON_ERROR),
            (string) $response->getContent(),
        );
    }

    /**
     * @test
     */
    public function it_calculates_a_rate_for_each_supported_shipping_method(): void
    {
        $dhl = $this->createShippingMethod('DHL');
        $ups = $this->createShippingMethod('UPS');

        $original = $this->createShippingMethod('ORIGINAL');

        $shipment = new Shipment();
        $shipment->setMethod($original);

        /** @var Stub&OrderInterface $cart */
        $cart = $this->createStub(OrderInterface::class);
        $cart->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $cart->method('getCurrencyCode')->willReturn('USD');

        /** @var Stub&ShippingMethodsResolverInterface $resolver */
        $resolver = $this->createStub(ShippingMethodsResolverInterface::class);
        $resolver->method('supports')->willReturn(true);
        $resolver->method('getSupportedMethods')->willReturn([$dhl, $ups]);

        // Keyed by method code, so a rate can only be correct if that method was applied first.
        $rates = ['DHL' => 2000, 'UPS' => 2500];

        /** @var Stub&DelegatingCalculatorInterface $calculator */
        $calculator = $this->createStub(DelegatingCalculatorInterface::class);
        $calculator->method('calculate')->willReturnCallback(
            static function (ShipmentInterface $subject) use ($rates): int {
                $method = $subject->getMethod();

                self::assertNotNull($method, 'The shipment must carry the method being priced.');

                return $rates[(string) $method->getCode()] ?? 0;
            },
        );

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler->expects(self::never())->method('handle');

        $controller = $this->createController(
            $viewHandler,
            new EventDispatcher(),
            $cart,
            $resolver,
            $calculator,
        );

        $response = $controller->estimateShipping(
            $this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']),
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => false,
                'options' => [
                    ['name' => 'DHL', 'rate' => '$20.00'],
                    ['name' => 'UPS', 'rate' => '$25.00'],
                ],
                'reason' => null,
            ], \JSON_THROW_ON_ERROR),
            (string) $response->getContent(),
        );

        self::assertSame($original, $shipment->getMethod(), 'The shipment should keep the method it arrived with.');
    }

    private function createMoneyFormatter(): MoneyFormatterInterface
    {
        /** @var Stub&MoneyFormatterInterface $moneyFormatter */
        $moneyFormatter = $this->createStub(MoneyFormatterInterface::class);
        $moneyFormatter->method('format')->willReturnCallback(
            static fn (int $amount): string => '$' . number_format($amount / 100, 2),
        );

        return $moneyFormatter;
    }

    /**
     * @test
     */
    public function it_leaves_the_carts_own_shipping_address_untouched(): void
    {
        $method = $this->createShippingMethod('DHL');

        $shipment = new Shipment();
        $shipment->setMethod($method);

        $existingAddress = new Address();
        $existingAddress->setCountryCode('CA');
        $existingAddress->setPostcode('V6B 1A1');

        $cart = new Order();
        $cart->setCurrencyCode('USD');
        $cart->setShippingAddress($existingAddress);
        $cart->addShipment($shipment);

        /** @var Stub&ShippingMethodsResolverInterface $resolver */
        $resolver = $this->createStub(ShippingMethodsResolverInterface::class);
        $resolver->method('supports')->willReturn(true);
        $resolver->method('getSupportedMethods')->willReturn([$method]);

        /** @var Stub&DelegatingCalculatorInterface $calculator */
        $calculator = $this->createStub(DelegatingCalculatorInterface::class);
        $calculator->method('calculate')->willReturn(2000);

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler->expects(self::never())->method('handle');

        $response = $this->createController($viewHandler, new EventDispatcher(), $cart, $resolver, $calculator)
            ->estimateShipping($this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']))
        ;

        // The estimate still ran against the submitted address.
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('$20.00', (string) $response->getContent());

        // ...but the cart is left exactly as it was found, so a flush cannot persist the estimate.
        self::assertSame($existingAddress, $cart->getShippingAddress());
        self::assertSame('CA', $cart->getShippingAddress()->getCountryCode());
    }

    /**
     * @test
     */
    public function it_restores_the_carts_shipping_address_when_the_estimate_cannot_be_completed(): void
    {
        $shipment = new Shipment();

        $cart = new Order();
        $cart->setCurrencyCode('USD');
        $cart->addShipment($shipment);

        // The cart had no shipping address of its own, which is the usual state for a fresh cart.
        self::assertNull($cart->getShippingAddress());

        /** @var Stub&ShippingMethodsResolverInterface $resolver */
        $resolver = $this->createStub(ShippingMethodsResolverInterface::class);
        $resolver->method('supports')->willReturn(false);

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler->expects(self::never())->method('handle');

        $response = $this->createController($viewHandler, new EventDispatcher(), $cart, $resolver)
            ->estimateShipping($this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']))
        ;

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('shipping_not_supported', (string) $response->getContent());

        // The early return happens inside the try, so only the finally can have reverted this.
        self::assertNull($cart->getShippingAddress());
    }

    /**
     * @test
     */
    public function it_marks_estimate_responses_as_uncacheable(): void
    {
        /** @var Stub&OrderInterface $cart */
        $cart = $this->createStub(OrderInterface::class);
        $cart->method('getShipments')->willReturn(new ArrayCollection());

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);

        $response = $this->createController($viewHandler, new EventDispatcher(), $cart)
            ->estimateShipping($this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']))
        ;

        $cacheControl = (string) $response->headers->get('Cache-Control');

        // One customer's rates must never be served to another from a shared cache.
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('private', $cacheControl);
    }

    /**
     * @test
     */
    public function it_refuses_an_estimate_once_the_client_has_used_up_its_allowance(): void
    {
        // A real limiter rather than a stub, so the allowance is genuinely consumed and exhausted.
        $limiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );

        /** @var Stub&OrderInterface $cart */
        $cart = $this->createStub(OrderInterface::class);
        $cart->method('getShipments')->willReturn(new ArrayCollection());

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);

        $controller = $this->createController(
            $viewHandler,
            new EventDispatcher(),
            $cart,
            null,
            null,
            $limiterFactory,
        );

        $request = $this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']);

        $allowed = $controller->estimateShipping($request);
        self::assertSame(Response::HTTP_OK, $allowed->getStatusCode());

        $refused = $controller->estimateShipping($request);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused->getStatusCode());
        self::assertStringContainsString('shipping_estimate_rate_limited', (string) $refused->getContent());
        self::assertTrue($refused->headers->has('Retry-After'));
        self::assertSame('1', $refused->headers->get('X-RateLimit-Limit'));
        self::assertStringContainsString('no-store', (string) $refused->headers->get('Cache-Control'));
    }

    /**
     * @test
     */
    public function it_does_not_limit_anything_without_a_limiter(): void
    {
        /** @var Stub&OrderInterface $cart */
        $cart = $this->createStub(OrderInterface::class);
        $cart->method('getShipments')->willReturn(new ArrayCollection());

        /** @var MockObject&ViewHandlerInterface $viewHandler */
        $viewHandler = $this->createMock(ViewHandlerInterface::class);

        $controller = $this->createController($viewHandler, new EventDispatcher(), $cart);

        $request = $this->createEstimateRequest(['country' => 'US', 'postcode' => '90802']);

        for ($i = 0; $i < 5; ++$i) {
            self::assertSame(Response::HTTP_OK, $controller->estimateShipping($request)->getStatusCode());
        }
    }

    private function createShippingMethod(string $code): ShippingMethodInterface
    {
        /** @var Stub&ShippingMethodInterface $method */
        $method = $this->createStub(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($code);
        $method->method('getName')->willReturn($code);

        return $method;
    }

    private function createController(
        ViewHandlerInterface $viewHandler,
        EventDispatcher $eventDispatcher,
        ?OrderInterface $cart = null,
        ?ShippingMethodsResolverInterface $shippingMethodsResolver = null,
        ?DelegatingCalculatorInterface $shippingCalculator = null,
        ?RateLimiterFactory $rateLimiterFactory = null,
    ): ShippingEstimatorController {
        /** @var Stub&MetadataInterface $metadata */
        $metadata = $this->createStub(MetadataInterface::class);

        /** @var Stub&RequestConfigurationFactoryInterface $requestConfigurationFactory */
        $requestConfigurationFactory = $this->createStub(RequestConfigurationFactoryInterface::class);
        $requestConfigurationFactory
            ->method('create')
            ->willReturnCallback(
                static fn (MetadataInterface $metadata, Request $request): RequestConfiguration => new RequestConfiguration(
                    $metadata,
                    $request,
                    new Parameters(['form' => ShippingEstimatorType::class]),
                ),
            )
        ;

        /** @var Stub&AddressFactoryInterface $addressFactory */
        $addressFactory = $this->createStub(AddressFactoryInterface::class);
        $addressFactory->method('createNew')->willReturnCallback(static fn (): Address => new Address());

        /** @var Stub&CartContextInterface $cartContext */
        $cartContext = $this->createStub(CartContextInterface::class);
        $cartContext->method('getCart')->willReturn($cart ?? $this->createStub(OrderInterface::class));

        $estimator = new EventDispatchingShippingEstimator(
            new ShippingEstimator(
                $shippingMethodsResolver ?? $this->createMock(ShippingMethodsResolverInterface::class),
                $shippingCalculator ?? $this->createMock(DelegatingCalculatorInterface::class),
            ),
            $eventDispatcher,
        );

        return new ShippingEstimatorController(
            $metadata,
            $requestConfigurationFactory,
            $cartContext,
            $viewHandler,
            $this->createFormFactory(),
            $this->createStub(Environment::class),
            $addressFactory,
            $estimator,
            new ShippingEstimateResponder($this->createMoneyFormatter()),
            $rateLimiterFactory,
        );
    }

    /**
     * Builds a form factory with only the types the estimator form needs, so the test does not
     * require a booted kernel or a database.
     */
    private function createFormFactory(): FormFactoryInterface
    {
        $unitedStates = new Country();
        $unitedStates->setCode('US');

        /** @var Stub&RepositoryInterface $countryRepository */
        $countryRepository = $this->createStub(RepositoryInterface::class);
        $countryRepository->method('getClassName')->willReturn(Country::class);
        $countryRepository->method('findBy')->willReturn([$unitedStates]);
        $countryRepository->method('findAll')->willReturn([$unitedStates]);
        $countryRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Country => ($criteria['code'] ?? null) === 'US' ? $unitedStates : null,
        );

        return Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            // The estimator form disables CSRF protection, an option that only exists once this extension is registered.
            ->addExtension(new CsrfExtension($this->createMock(CsrfTokenManagerInterface::class)))
            ->addExtension(new PreloadedExtension([
                new ShippingEstimatorType(),
                new CountryCodeChoiceType($countryRepository),
                new CountryChoiceType($countryRepository),
            ], []))
            ->getFormFactory()
        ;
    }

    /**
     * @param array<string, string> $query
     */
    private function createEstimateRequest(array $query): Request
    {
        $request = Request::create('/ajax/estimate-shipping', Request::METHOD_GET, $query);
        $request->setRequestFormat('json');

        return $request;
    }
}
