<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app/Common',
        __DIR__ . '/app/Health',
        __DIR__ . '/app/Question',
        __DIR__ . '/app/Ai',
        __DIR__ . '/app/Auth',
        __DIR__ . '/app/Game',
        __DIR__ . '/tests',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/runtime/.php-cs-fixer.cache');
