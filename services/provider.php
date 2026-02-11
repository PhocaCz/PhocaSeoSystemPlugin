<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Phoca\Plugin\System\PhocaSeo\Extension\PhocaSeo;

return new class () implements ServiceProviderInterface {

    public function register(Container $container): void {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                // Get the database from container
                $db = $container->get(\Joomla\Database\DatabaseInterface::class);

                $plugin = new PhocaSeo(
                    (array) PluginHelper::getPlugin('system', 'phocaseo')
                );

                $plugin->setDatabase($db);

                return $plugin;
            }
        );
    }
};
