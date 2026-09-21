<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Http;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns an estimate into the response the endpoint sends.
 */
interface ShippingEstimateResponderInterface
{
    public function respond(ShippingEstimate $estimate, Request $request): Response;
}
