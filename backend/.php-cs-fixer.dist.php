<?php

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->append([__FILE__])
    ->in([
        __DIR__.'/config',
        __DIR__.'/fixtures',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->notPath([
        'bundles.php',
        'reference.php',
    ]);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => [
            'statements' => [
                'break', 'case', 'continue', 'declare', 'default', 'exit', 'goto',
                'if', 'include', 'include_once', 'phpdoc', 'require', 'require_once',
                'return', 'switch', 'throw', 'try', 'yield', 'yield_from',
            ],
        ],
        'class_attributes_separation' => [
            'elements' => ['const' => 'one', 'property' => 'one', 'method' => 'one'],
        ],
        'concat_space' => ['spacing' => 'none'],
        'declare_strict_types' => true,
        'doctrine_annotation_spaces' => [
            'after_array_assignments_equals' => false,
            'before_array_assignments_equals' => false,
        ],
        'global_namespace_import' => true,
        'method_argument_space' => true,
        'native_function_invocation' => false,
        'not_operator_with_space' => false,
        'php_unit_method_casing' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        'single_line_throw' => false,
        'void_return' => true,
    ])
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setFinder($finder);
