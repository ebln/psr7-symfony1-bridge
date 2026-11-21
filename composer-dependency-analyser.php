<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

// The symfony1 classes are in the public signatures, but the package is not in "require":
// the bridge supports every fork from 1.5.23 and declares that through "conflict".
return (new Configuration())
    ->ignoreErrorsOnPackage('friendsofsymfony1/symfony1', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
