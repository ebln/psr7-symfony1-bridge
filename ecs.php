<?php

declare(strict_types=1);

use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet\RuleSet;
use Symplify\EasyCodingStandard\Config\ECSConfig;

// The scripts and the workflow pass --blink, so the Go engine checks the style in every environment.

// ECS has no loader for php-cs-fixer's "@" sets, so this file expands "@Symfony" itself.
$factory = new FixerFactory();
$factory->registerBuiltInFixers();
$classes = [];
foreach ($factory->getFixers() as $fixer) {
    $classes[$fixer->getName()] = get_class($fixer);
}

$rules = (new RuleSet(['@Symfony' => true]))->getRules();
// PHP 7.4 cannot parse a trailing comma in a parameter list; "parameters" and "match" stay out.
$rules['trailing_comma_in_multiline'] = ['after_heredoc' => true, 'elements' => ['array_destructuring', 'arrays']];

$config = ECSConfig::configure()->withPaths([__DIR__.'/src', __DIR__.'/tests'])->withRootFiles();
$plain = [];
foreach ($rules as $name => $options) {
    if (true === $options) {
        $plain[] = $classes[$name];
    } else {
        $config = $config->withConfiguredRule($classes[$name], $options);
    }
}

// withRules() replaces the list, so it is called once.
return $config->withRules($plain);
