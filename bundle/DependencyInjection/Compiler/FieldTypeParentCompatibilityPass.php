<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Ensures the abstract 'ezpublish.fieldType' parent service and renamed
 * 'ezrichtext.*' / 'ezpublish.*' service IDs are available on Ibexa 4.x.
 * Runs before Symfony's ResolveChildDefinitionsPass so child services that
 * declare parent: ezpublish.fieldType do not fail to compile.
 */
class FieldTypeParentCompatibilityPass implements CompilerPassInterface
{
    /** @var array<string,string> old service ID → new service ID or FQCN */
    private const SERVICE_MAP = [
        'ezpublish.fieldType' => 'Ibexa\Core\FieldType\FieldType',
        // ezrichtext renamed services
        'ezrichtext.validator.docbook' => 'ibexa.richtext.validator.docbook',
        'ezrichtext.converter.xhtml5.output' => 'ibexa.richtext.converter.xhtml5.output',
        'ezrichtext.converter.xhtml5.input' => 'ibexa.richtext.converter.xhtml5.input',
        'ezrichtext.api.service.richtext' => 'ibexa.richtext.api.service.richtext',
        'ezrichtext.persistence.gateway.legacy' => 'ibexa.richtext.persistence.gateway.legacy',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::SERVICE_MAP as $legacyId => $ibexaId) {
            if ($container->hasDefinition($legacyId) || $container->hasAlias($legacyId)) {
                continue;
            }

            if ($container->hasDefinition($ibexaId)) {
                $container->setDefinition($legacyId, clone $container->getDefinition($ibexaId));
            } elseif ($container->hasAlias($ibexaId)) {
                $container->setAlias($legacyId, (string) $container->getAlias($ibexaId));
            } else {
                $container->setAlias($legacyId, $ibexaId);
            }
        }
    }
}
