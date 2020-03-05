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

namespace CoreShop\Component\Address\Context\RequestBased;

use CoreShop\Component\Address\Context\CountryNotFoundException;
use CoreShop\Component\Address\Model\CountryInterface;
use CoreShop\Component\Address\Repository\CountryRepositoryInterface;
use GeoIp2\Database\Reader;
use Pimcore\Cache\Core\CoreHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

final class GeoLiteBasedRequestResolver implements RequestResolverInterface
{
    /**
     * @var CountryRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var CoreHandlerInterface
     */
    private $cache;

    /**
     * @var string
     */
    private $geoDbFile;

    /**
     * @var string
     */
    private $geoDbFallbackFile;

    public function __construct(CountryRepositoryInterface $countryRepository, CoreHandlerInterface $cache, string $geoDbFile = null)
    {
        $this->countryRepository = $countryRepository;
        $this->cache = $cache;
        $this->geoDbFile = $geoDbFile;
        $this->geoDbFallbackFile = PIMCORE_CONFIGURATION_DIRECTORY . '/GeoLite2-City.mmdb';
    }

    /**
     * {@inheritdoc}
     */
    public function findCountry(Request $request): CountryInterface
    {
        $geoDbFileLocation = $this->geoDbFile;

        if (null === $geoDbFileLocation || !file_exists($geoDbFileLocation)) {
            @trigger_error(
                'You are still using the default search path for the MaxMind GEO DB File. Pimcore introduced a new parameter for the file, use that instead.',
                E_USER_DEPRECATED
            );

            $geoDbFileLocation = $this->geoDbFallbackFile;
        }

        if (null === $geoDbFileLocation || !file_exists($geoDbFileLocation)) {
            throw new CountryNotFoundException();
        }

        $record = null;
        $isoCode = null;
        $clientIp = $request->getClientIp();

        if ($this->checkIfIpIsPrivate($clientIp)) {
            throw new CountryNotFoundException();
        }

        $cacheKey = sprintf('geo_lite_ip_%s', md5($clientIp));

        if ($countryIsoCode = $this->cache->load($cacheKey)) {
            $country = $this->countryRepository->findByCode($countryIsoCode);

            if ($country instanceof CountryInterface) {
                return $country;
            }
        }

        $countryIsoCode = $this->guessCountryByGeoLite($clientIp, $geoDbFileLocation);

        if ($countryIsoCode === null) {
            throw new CountryNotFoundException();
        }

        $country = $this->countryRepository->findByCode($countryIsoCode);

        if (!$country instanceof CountryInterface) {
            throw new CountryNotFoundException();
        }

        $this->cache->save($cacheKey, $countryIsoCode, [], 24*60*60);

        return $country;
    }

    /**
     * @param string $clientIp
     * @param string $geoDbFileLocation
     *
     * @return string|null
     */
    private function guessCountryByGeoLite($clientIp, $geoDbFileLocation): ?string
    {
        try {
            $reader = new Reader($geoDbFileLocation);
            $record = $reader->city($clientIp);
            return $record->country->isoCode;
        } catch (\Exception $e) {
            //If something goes wrong, ignore the exception and throw a CountryNotFoundException
        }

        return null;
    }

    /**
     * Check if ip is private.
     *
     * @param string $clientIp
     *
     * @return bool
     */
    private function checkIfIpIsPrivate($clientIp): bool
    {
        $privateAddresses = [
            '10.0.0.0|10.255.255.255', // single class A network
            '172.16.0.0|172.31.255.255', // 16 contiguous class B network
            '192.168.0.0|192.168.255.255', // 256 contiguous class C network
            '169.254.0.0|169.254.255.255', // Link-local address also refered to as Automatic Private IP Addressing
            '127.0.0.0|127.255.255.255', // localhost
        ];

        $longIp = ip2long($clientIp);
        if ($longIp !== -1) {
            foreach ($privateAddresses as $priAddr) {
                list($start, $end) = explode('|', $priAddr);

                // IF IS PRIVATE
                if ($longIp >= ip2long($start) && $longIp <= ip2long($end)) {
                    return true;
                }
            }
        }

        return false;
    }
}
