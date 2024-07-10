<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*      web : https://www.openstudio.fr */

/*      For the full copyright and license information, please view the LICENSE */
/*      file that was distributed with this source code. */

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 17/11/2023.
 */

namespace Brevo\Trait;

use Brevo\Brevo;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

trait DataExtractorTrait
{
    public function getMappedValues(
        array $jsonMapping,
        string $mapKey,
        string $sourceTableName,
        string $selectorFieldName,
        mixed $selector,
        int $selectorType = \PDO::PARAM_INT
    ): array {
        try {
            if (empty($jsonMapping)) {
                return [];
            }

            if (empty($jsonMapping[$mapKey] || ! is_array($jsonMapping[$mapKey]))) {
                return [];
            }

            $attributes = [];

            /** @var ConnectionWrapper $con */
            $con = Propel::getConnection();

            foreach ($jsonMapping[$mapKey] as $key => $dataQuery) {
                if (empty($dataQuery['select'])) {
                    throw new \Exception("Mapping error : 'select' element missing in ".$key.' query');
                }

                try {
                    $sql = 'SELECT '.$dataQuery['select'].' AS '.$key.' FROM '.$sourceTableName;

                    if (! empty($dataQuery['join'])) {
                        if (!\is_array($dataQuery['join'])) {
                            $dataQuery['join'] = [$dataQuery['join']];
                        }

                        foreach ($dataQuery['join'] as $join) {
                            $sql .= ' LEFT JOIN '.$join;
                        }
                    }

                    $sql .= ' WHERE '.$selectorFieldName.' = :selector';

                    if (! empty($dataQuery['groupBy'])) {
                        $sql .= ' GROUP BY '.$dataQuery['groupBy'];
                    }

                    $stmt = $con->prepare($sql);
                    $stmt->bindValue(':selector', $selector, $selectorType);
                    $stmt->execute();

                    // Decode flags
                    $flags = [];
                    if (! empty($dataQuery['flags']) && \is_array($dataQuery['flags'])) {
                        foreach ($dataQuery['flags'] as $flagDesc) {
                            $flags[$flagDesc['type']] = $flagDesc['arg'] ?? '';
                        }
                    }

                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $value = $row[$key] ?? '';

                        // Process flags
                        foreach ($flags as $name => $arg) {
                            switch ($name) {
                                case 'strip_tags':
                                    $value = strip_tags($value);
                                    break;
                                case 'html_entity_decode':
                                    $value = html_entity_decode($value, \ENT_QUOTES, 'UTF-8');
                                    break;
                                case 'truncate':
                                    $value = mb_substr($value, 0, (int) $arg);
                                    break;
                                case 'ellipsis':
                                    $value = $this->truncate($value, (int) $arg);
                                    break;
                                default:
                                    Tlog::getInstance()->warning("Undefined flag : $name");
                                    break;
                            }
                        }

                        $attributes[$key] = $value;

                        if (\array_key_exists($key, $jsonMapping) && \array_key_exists($value, $jsonMapping[$key])) {
                            $attributes[$key] = $jsonMapping[$key][$value];
                        }
                    }
                } catch (\Exception $ex) {
                    Tlog::getInstance()->error(
                        'Failed to execute SQL request to map Brevo attribute "'.$key.'". Error is '.$ex->getMessage().", request is : $sql");
                }
            }

            return $attributes;
        } catch (\Exception $ex) {
            throw new TheliaProcessException(
                'Mapping error : configuration is missing or invalid, please go to the module configuration and define the JSON mapping to match thelia attribute with brevo attribute. Error is : '.$ex->getMessage()
            );
        }
    }

    public function getCustomerAttribute($customerId): array
    {
        $mappingString = ConfigQuery::read(Brevo::BREVO_ATTRIBUTES_MAPPING);

        if (empty($mappingString)) {
            return [];
        }

        if (null === $mapping = json_decode($mappingString, true)) {
            throw new TheliaProcessException('Customer attribute mapping error: JSON data seems invalid, pleas echeck syntax.');
        }

        return $this->getMappedValues(
            $mapping,
            'customer_query',
            'customer',
            'customer.id',
            $customerId,
        );
    }

    /**
     * Truncates a string to a certain char length, stopping on a word.
     *
     * @param $string
     * @param $length
     * @return mixed|string
     */
    protected function truncate($string, $length) {
        //
        if (mb_strlen($string) > $length) {
            //limit hit!
            $string = mb_substr($string,0, ($length - 1));

            //stop on a word.
            $string = mb_substr($string,0, mb_strrpos($string,' ')).'…';
        }

        return $string;
    }
}
