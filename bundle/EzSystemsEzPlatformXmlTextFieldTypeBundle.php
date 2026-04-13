<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle;

use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Compiler\XmlTextConverterPass;
use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Configuration\Parser as ConfigParser;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EzSystemsEzPlatformXmlTextFieldTypeBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new XmlTextConverterPass());

        $ibexaExtension = $container->getExtension('ibexa');
        $ibexaExtension->addConfigParser(new ConfigParser\FieldType\XmlText());
        $ibexaExtension->addDefaultSettings(__DIR__ . '/Resources/config', ['default_settings.yml']);
    }
}
