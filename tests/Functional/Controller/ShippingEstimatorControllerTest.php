<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Functional\Controller;

use BabDev\SyliusShippingEstimatePlugin\Controller\ShippingEstimatorController;
use BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent;
use BabDev\SyliusShippingEstimatePlugin\Form\Type\ShippingEstimatorType;
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
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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

    private function createController(
        ViewHandlerInterface $viewHandler,
        EventDispatcher $eventDispatcher,
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
        $cartContext->method('getCart')->willReturn($this->createStub(OrderInterface::class));

        $controller = new ShippingEstimatorController(
            $metadata,
            $requestConfigurationFactory,
            $cartContext,
            $viewHandler,
            $addressFactory,
            $this->createMock(AdjustmentFactoryInterface::class),
            $this->createMock(ShippingMethodsResolverInterface::class),
            $this->createMock(DelegatingCalculatorInterface::class),
            $this->createMock(MoneyFormatterInterface::class),
            $eventDispatcher,
        );

        $container = new Container();
        $container->set('form.factory', $this->createFormFactory());

        $controller->setContainer($container);

        return $controller;
    }

    /**
     * Builds a form factory with only the types the estimator form needs, so the test does not
     * require a booted kernel or a database.
     */
    private function createFormFactory(): FormFactoryInterface
    {
        $unitedStates = new Country();
        $unitedStates->setCode('US');

        /** @var Stub&RepositoryInterface<CountryInterface> $countryRepository */
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
