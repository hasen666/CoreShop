<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2020 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Component\Core\Order\Processor;

use CoreShop\Component\Order\Model\AdjustmentInterface;
use CoreShop\Component\Order\Model\CartInterface;
use CoreShop\Component\Order\Processor\CartProcessorInterface;
use CoreShop\Component\Resource\Factory\FactoryInterface;

final class CartPaymentProcessor implements CartProcessorInterface
{
    /**
     * @var int
     */
    private $decimalFactor;

    /**
     * @var int
     */
    private $decimalPrecision;

    /**
     * @param int              $decimalFactor
     * @param int              $decimalPrecision
     */
    public function __construct(int $decimalFactor, int $decimalPrecision)
    {
        $this->decimalFactor = $decimalFactor;
        $this->decimalPrecision = $decimalPrecision;
    }

    public function process(CartInterface $cart)
    {
        $cart->setPaymentTotal(
            (int) round((round($cart->getTotal() / $this->decimalFactor, $this->decimalPrecision) * 100), 0)
        );
    }
}
