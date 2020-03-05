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

declare(strict_types=1);

namespace CoreShop\Bundle\CoreBundle\Templating\Helper;

use CoreShop\Component\Core\Context\ShopperContextInterface;
use CoreShop\Component\Core\Product\ProductTaxCalculatorFactoryInterface;
use CoreShop\Component\Core\Provider\DefaultTaxAddressProviderInterface;
use CoreShop\Component\Order\Model\PurchasableInterface;
use CoreShop\Component\Taxation\Calculator\TaxCalculatorInterface;
use Symfony\Component\Templating\Helper\Helper;

class ProductTaxHelper extends Helper implements ProductTaxHelperInterface
{
    private $priceHelper;
    private $taxCalculatorFactory;
    private $defaultAddressProvider;

    public function __construct(
        ProductPriceHelperInterface $priceHelper,
        ProductTaxCalculatorFactoryInterface $taxCalculatorFactory,
        DefaultTaxAddressProviderInterface $defaultAddressProvider
    ) {
        $this->priceHelper = $priceHelper;
        $this->taxCalculatorFactory = $taxCalculatorFactory;
        $this->defaultAddressProvider = $defaultAddressProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getTaxAmount(PurchasableInterface $product, array $context = []): int
    {
        $taxCalculator = $this->taxCalculatorFactory->getTaxCalculator($product, $this->defaultAddressProvider->getAddress($context));

        if ($taxCalculator instanceof TaxCalculatorInterface) {
            return $taxCalculator->getTaxesAmount($this->priceHelper->getPrice($product, false, $context));
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getTaxRate(PurchasableInterface $product, array $context = []): float
    {
        $taxCalculator = $this->taxCalculatorFactory->getTaxCalculator($product, $this->defaultAddressProvider->getAddress($context));

        if ($taxCalculator instanceof TaxCalculatorInterface) {
            return $taxCalculator->getTotalRate();
        }

        return 0;
    }

    public function getName(): string
    {
        return 'coreshop_product_tax';
    }
}
