<?php
/**
 * Integration Test Configuration
 * 
 * Configuration for testing across different WordPress versions,
 * SEO plugins, themes, and page builders.
 */

return [
    'wordpress_versions' => [
        '5.8' => [
            'features' => ['basic_media_library', 'classic_editor'],
            'limitations' => ['no_block_editor_enhancements'],
            'test_focus' => ['core_functionality', 'backward_compatibility']
        ],
        '5.9' => [
            'features' => ['enhanced_media_library', 'improved_block_editor'],
            'limitations' => ['limited_theme_json'],
            'test_focus' => ['media_handling', 'block_editor_integration']
        ],
        '6.0' => [
            'features' => ['full_site_editing', 'theme_json_v2'],
            'limitations' => [],
            'test_focus' => ['fse_compatibility', 'theme_integration']
        ],
        '6.1' => [
            'features' => ['enhanced_performance', 'improved_accessibility'],
            'limitations' => [],
            'test_focus' => ['performance', 'accessibility']
        ],
        '6.2' => [
            'features' => ['design_tools', 'enhanced_navigation'],
            'limitations' => [],
            'test_focus' => ['ui_integration', 'navigation_compatibility']
        ],
        '6.3' => [
            'features' => ['command_palette', 'enhanced_patterns'],
            'limitations' => [],
            'test_focus' => ['modern_features', 'pattern_integration']
        ],
        '6.4' => [
            'features' => ['latest_features', 'performance_improvements'],
            'limitations' => [],
            'test_focus' => ['cutting_edge_features', 'optimization']
        ]
    ],

    'seo_plugins' => [
        'rank_math' => [
            'class' => 'RankMath\\Helper',
            'functions' => [
                'get_meta' => 'RankMath\\Helper::get_meta',
                'get_focus_keyword' => 'RankMath\\Helper::get_focus_keyword',
                'get_title' => 'RankMath\\Helper::get_title'
            ],
            'meta_keys' => [
                'rank_math_focus_keyword',
                'rank_math_title',
                'rank_math_description'
            ],
            'test_scenarios' => [
                'focus_keyword_extraction',
                'meta_title_integration',
                'description_analysis'
            ]
        ],
        'yoast' => [
            'class' => 'WPSEO_Options',
            'functions' => [
                'get_meta' => 'WPSEO_Meta::get_value',
                'get_focus_keyword' => 'WPSEO_Meta::get_value',
                'get_title' => 'WPSEO_Meta::get_value'
            ],
            'meta_keys' => [
                '_yoast_wpseo_focuskw',
                '_yoast_wpseo_title',
                '_yoast_wpseo_metadesc'
            ],
            'test_scenarios' => [
                'focus_keyword_extraction',
                'readability_score_integration',
                'seo_score_consideration'
            ]
        ],
        'all_in_one_seo' => [
            'class' => 'AIOSEO\\Plugin\\Common\\Main',
            'functions' => [
                'get_meta' => 'AIOSEO\\Plugin\\Common\\Meta\\Meta::getMetaData',
                'get_title' => 'AIOSEO\\Plugin\\Common\\Meta\\Title::getTitle',
                'get_description' => 'AIOSEO\\Plugin\\Common\\Meta\\Description::getDescription'
            ],
            'meta_keys' => [
                '_aioseo_title',
                '_aioseo_description',
                '_aioseo_keywords'
            ],
            'test_scenarios' => [
                'title_template_integration',
                'schema_markup_consideration',
                'social_meta_integration'
            ]
        ],
        'seopress' => [
            'class' => 'SEOPress_Options',
            'functions' => [
                'get_meta' => 'get_post_meta',
                'get_title' => 'seopress_get_title',
                'get_description' => 'seopress_get_description'
            ],
            'meta_keys' => [
                '_seopress_titles_title',
                '_seopress_titles_desc',
                '_seopress_analysis_target_kw'
            ],
            'test_scenarios' => [
                'content_analysis_integration',
                'breadcrumb_consideration',
                'local_seo_integration'
            ]
        ]
    ],

    'themes' => [
        'twentytwentyone' => [
            'type' => 'default',
            'features' => ['classic_theme', 'custom_css'],
            'media_library_customizations' => false,
            'test_focus' => ['basic_compatibility', 'default_styling']
        ],
        'twentytwentytwo' => [
            'type' => 'block_theme',
            'features' => ['full_site_editing', 'theme_json'],
            'media_library_customizations' => false,
            'test_focus' => ['fse_compatibility', 'block_theme_integration']
        ],
        'twentytwentythree' => [
            'type' => 'block_theme',
            'features' => ['enhanced_fse', 'modern_theme_json'],
            'media_library_customizations' => false,
            'test_focus' => ['modern_features', 'accessibility']
        ],
        'astra' => [
            'type' => 'multipurpose',
            'features' => ['customizer_integration', 'performance_optimized'],
            'media_library_customizations' => true,
            'test_focus' => ['customizer_compatibility', 'performance_impact']
        ],
        'generatepress' => [
            'type' => 'lightweight',
            'features' => ['minimal_css', 'hook_system'],
            'media_library_customizations' => false,
            'test_focus' => ['lightweight_integration', 'hook_compatibility']
        ],
        'oceanwp' => [
            'type' => 'multipurpose',
            'features' => ['extensive_customization', 'woocommerce_integration'],
            'media_library_customizations' => true,
            'test_focus' => ['customization_compatibility', 'ecommerce_integration']
        ]
    ],

    'page_builders' => [
        'elementor' => [
            'class' => 'Elementor\\Plugin',
            'content_structure' => 'json_meta',
            'meta_key' => '_elementor_data',
            'extraction_method' => 'json_decode',
            'test_scenarios' => [
                'widget_content_extraction',
                'image_widget_analysis',
                'text_widget_context'
            ]
        ],
        'gutenberg' => [
            'class' => 'WP_Block_Editor_Context',
            'content_structure' => 'block_content',
            'meta_key' => 'post_content',
            'extraction_method' => 'parse_blocks',
            'test_scenarios' => [
                'block_content_extraction',
                'media_block_analysis',
                'gallery_block_context'
            ]
        ],
        'beaver_builder' => [
            'class' => 'FLBuilder',
            'content_structure' => 'serialized_meta',
            'meta_key' => '_fl_builder_data',
            'extraction_method' => 'unserialize',
            'test_scenarios' => [
                'module_content_extraction',
                'photo_module_analysis',
                'text_module_context'
            ]
        ],
        'divi' => [
            'class' => 'ET_Builder_Element',
            'content_structure' => 'shortcode_content',
            'meta_key' => 'post_content',
            'extraction_method' => 'shortcode_parse',
            'test_scenarios' => [
                'module_shortcode_extraction',
                'image_module_analysis',
                'text_module_context'
            ]
        ],
        'visual_composer' => [
            'class' => 'Vc_Manager',
            'content_structure' => 'shortcode_content',
            'meta_key' => 'post_content',
            'extraction_method' => 'shortcode_parse',
            'test_scenarios' => [
                'element_content_extraction',
                'single_image_analysis',
                'text_block_context'
            ]
        ]
    ],

    'test_scenarios' => [
        'single_media_rename' => [
            'description' => 'Test AI rename functionality for single media files',
            'requirements' => ['1.1', '1.2', '1.3', '1.4', '1.5'],
            'test_data' => [
                'image_file' => 'test-image.jpg',
                'pdf_file' => 'test-document.pdf',
                'office_file' => 'test-presentation.pptx'
            ]
        ],
        'bulk_media_rename' => [
            'description' => 'Test AI bulk rename functionality',
            'requirements' => ['2.1', '2.2', '2.3', '2.4', '2.5'],
            'test_data' => [
                'multiple_images' => ['img1.jpg', 'img2.png', 'img3.gif'],
                'mixed_files' => ['doc.pdf', 'img.jpg', 'pres.pptx']
            ]
        ],
        'content_analysis' => [
            'description' => 'Test content analysis across different file types',
            'requirements' => ['3.1', '3.2', '3.3', '3.4', '3.5'],
            'test_data' => [
                'ocr_images' => ['text-image.jpg', 'screenshot.png'],
                'pdf_documents' => ['text-document.pdf', 'scanned-document.pdf'],
                'office_documents' => ['document.docx', 'spreadsheet.xlsx']
            ]
        ],
        'context_extraction' => [
            'description' => 'Test context extraction from posts and pages',
            'requirements' => ['4.1', '4.2', '4.3', '4.4', '4.5'],
            'test_data' => [
                'posts_with_media' => [101, 102, 103],
                'pages_with_media' => [201, 202, 203],
                'page_builder_content' => [301, 302, 303]
            ]
        ],
        'credit_management' => [
            'description' => 'Test credit system functionality',
            'requirements' => ['5.1', '5.2', '5.3', '5.4', '5.5'],
            'test_data' => [
                'new_user_credits' => 5,
                'credit_deduction' => 1,
                'insufficient_credits' => 0
            ]
        ],
        'error_handling' => [
            'description' => 'Test error handling and fallback mechanisms',
            'requirements' => ['6.1', '6.2', '6.3', '6.4'],
            'test_data' => [
                'api_timeout' => true,
                'invalid_api_key' => true,
                'network_error' => true
            ]
        ],
        'history_tracking' => [
            'description' => 'Test operation history and tracking',
            'requirements' => ['7.1', '7.2', '7.3', '7.4', '7.5'],
            'test_data' => [
                'ai_operations' => ['single_rename', 'bulk_rename'],
                'manual_operations' => ['manual_rename'],
                'credit_transactions' => ['deduct', 'add']
            ]
        ]
    ],

    'compatibility_matrix' => [
        'wordpress_5.8' => [
            'supported_themes' => ['twentytwentyone', 'astra', 'generatepress'],
            'supported_seo_plugins' => ['yoast', 'rank_math'],
            'supported_page_builders' => ['gutenberg', 'elementor']
        ],
        'wordpress_6.0' => [
            'supported_themes' => ['twentytwentyone', 'twentytwentytwo', 'astra', 'generatepress', 'oceanwp'],
            'supported_seo_plugins' => ['yoast', 'rank_math', 'all_in_one_seo'],
            'supported_page_builders' => ['gutenberg', 'elementor', 'beaver_builder']
        ],
        'wordpress_6.4' => [
            'supported_themes' => ['twentytwentyone', 'twentytwentytwo', 'twentytwentythree', 'astra', 'generatepress', 'oceanwp'],
            'supported_seo_plugins' => ['yoast', 'rank_math', 'all_in_one_seo', 'seopress'],
            'supported_page_builders' => ['gutenberg', 'elementor', 'beaver_builder', 'divi', 'visual_composer']
        ]
    ],

    'performance_benchmarks' => [
        'single_rename_max_time' => 30, // seconds
        'bulk_rename_max_time_per_file' => 35, // seconds
        'content_analysis_max_time' => 15, // seconds
        'context_extraction_max_time' => 10, // seconds
        'max_memory_usage' => '256M',
        'max_database_queries' => 50
    ],

    'test_environments' => [
        'local' => [
            'wp_debug' => true,
            'wp_debug_log' => true,
            'script_debug' => true
        ],
        'staging' => [
            'wp_debug' => false,
            'wp_debug_log' => true,
            'script_debug' => false
        ],
        'production' => [
            'wp_debug' => false,
            'wp_debug_log' => false,
            'script_debug' => false
        ]
    ]
];