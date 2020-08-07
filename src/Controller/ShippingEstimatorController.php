<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Controller;

use FOS\RestBundle\View\View;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShippingEstimatorController
{
    /** @var MetadataInterface */
    private $metadata;

    /** @var RequestConfigurationFactoryInterface */
    private $requestConfigurationFactory;

    /** @var CartContextInterface */
    private $cartContext;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var FormFactoryInterface */
    private $formFactory;

    public function __construct(
        MetadataInterface $metadata,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        CartContextInterface $cartContext,
        ViewHandlerInterface $viewHandler,
        FormFactoryInterface $formFactory
    ) {
        $this->metadata = $metadata;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->cartContext = $cartContext;
        $this->viewHandler = $viewHandler;
        $this->formFactory = $formFactory;
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

        $view = View::create()
            ->setTemplate($configuration->getTemplate('_widget.html'))
            ->setData([
                'cart' => $cart,
                'form' => $form->createView(),
            ])
        ;

        return $this->viewHandler->handle($configuration, $view);
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

        return $this->formFactory->createNamed('', $formType, null, array_merge($formOptions, ['csrf_protection' => false]));
    }
}
