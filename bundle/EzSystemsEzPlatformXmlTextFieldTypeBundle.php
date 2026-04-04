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

use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Compiler\FieldTypeParentCompatibilityPass;
use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Compiler\XmlTextConverterPass;
use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Configuration\Parser as ConfigParser;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EzSystemsEzPlatformXmlTextFieldTypeBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // Must run before Symfony's ResolveChildDefinitionsPass (TYPE_BEFORE_OPTIMIZATION, priority 0)
        // so that ezpublish.fieldType parent exists when child services are resolved on Ibexa 4.x.
        $container->addCompilerPass(new FieldTypeParentCompatibilityPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $container->addCompilerPass(new XmlTextConverterPass());

        $eZExtension = $container->hasExtension('ibexa')
            ? $container->getExtension('ibexa')
            : $container->getExtension('ezpublish');
        $eZExtension->addConfigParser(new ConfigParser\FieldType\XmlText());
        $eZExtension->addDefaultSettings(__DIR__ . '/Resources/config', ['default_settings.yml']);
    }
}
