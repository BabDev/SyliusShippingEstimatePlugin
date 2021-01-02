<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Controller;

use FOS\RestBundle\View\View;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShippingEstimatorController extends AbstractController
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var RequestConfigurationFactoryInterface */
    private $requestConfigurationFactory;

    /** @var CartContextInterface */
    private $cartContext;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        CartContextInterface $cartContext,
        ViewHandlerInterface $viewHandler
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->cartContext = $cartContext;
        $this->viewHandler = $viewHandler;
    }

    public function estimateShippingAction(Request $request): Response
    {
        throw new \Exception('Not yet implemented');
    }

    public function renderWidgetAction(Request $request): Response
    {
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $cart = $this->cartContext->getCart();

        $form = $this->createEstimatorForm($configuration);

        if (!$configuration->isHtmlRequest()) {
            return $this->viewHandler->handle($configuration, View::create($form));
        }

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
