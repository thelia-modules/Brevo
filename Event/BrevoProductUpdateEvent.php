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
 * Date: 15/11/2023.
 */

namespace Brevo\Event;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Product;

class BrevoProductUpdateEvent extends ActionEvent
{
    public function __construct(protected Product $product)
    {
    }

    public function getProduct(): Product
    {
        return $this->product;
    }
}
