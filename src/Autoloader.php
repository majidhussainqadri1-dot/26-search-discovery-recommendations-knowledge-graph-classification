<?php

declare(strict_types=1);

namespace Sabri\File26;

final class Autoloader
{
    private const PREFIX = 'Sabri\\File26\\';

    /**
     * Phase 26E intentionally groups tightly coupled runtime classes into reviewed
     * bundle files. This map is the canonical bridge between PSR-style class names
     * and those immutable bundles; ordinary one-class files are still preferred.
     *
     * @var array<string,list<string>>
     */
    private const BUNDLES = [
        'Sabri\\File26\\Api\\' => [
            'Runtime/ApiSupport.php',
            'Runtime/ApiPublic.php',
            'Runtime/ApiAdmin.php',
        ],
        'Sabri\\File26\\Application\\' => [
            'Runtime/ApplicationSearch.php',
            'Runtime/ApplicationDiscovery.php',
            'Runtime/ApplicationRuntime.php',
        ],
        'Sabri\\File26\\Classification\\' => ['Runtime/Classification.php'],
        'Sabri\\File26\\Governance\\' => [
            'Runtime/GovernanceCore.php',
            'Runtime/GovernanceEvaluation.php',
            'Runtime/GovernanceExport.php',
            'Runtime/GovernanceOperations.php',
        ],
        'Sabri\\File26\\Ingestion\\' => ['Runtime/Ingestion.php'],
        'Sabri\\File26\\KnowledgeGraph\\' => ['Runtime/KnowledgeGraph.php'],
        'Sabri\\File26\\Query\\' => ['Runtime/Query.php'],
        'Sabri\\File26\\Ranking\\' => ['Runtime/Ranking.php'],
        'Sabri\\File26\\Recommendations\\' => ['Runtime/Recommendations.php'],
        'Sabri\\File26\\Taxonomy\\' => ['Runtime/Taxonomy.php'],
        'Sabri\\File26\\Adapters\\' => ['Runtime/OwnerIntegration.php'],
        'Sabri\\File26\\Connectors\\' => ['Runtime/OwnerIntegration.php'],
        'Sabri\\File26\\Registry\\DefaultConnectorRegistrar' => ['Runtime/OwnerIntegration.php'],
    ];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load'], true, true);
    }

    private static function load(string $class): void
    {
        if (! str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        if ($relative === false || $relative === '') {
            return;
        }

        $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
            return;
        }

        foreach (self::BUNDLES as $classPrefix => $files) {
            if (! str_starts_with($class, $classPrefix)) {
                continue;
            }

            foreach ($files as $file) {
                $bundle = __DIR__ . '/' . $file;
                if (is_file($bundle)) {
                    require_once $bundle;
                }
            }
            return;
        }
    }
}
