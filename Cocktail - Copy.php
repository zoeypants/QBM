<?php

namespace VMG_Studios\QBM\Post_Types;

use VMG_Studios\QBM\Taxonomies;

class Cocktail {
    public const POST_TYPE = 'qbm-cocktail';

    public function __construct() {
        register_post_type(self::POST_TYPE, [
            'label'  => esc_html__('Cocktails', 'qbm'),
            'labels' => [
                'menu_name'                  => esc_html__('Cocktails', 'qbm'),
                'all_items'                  => esc_html__('All Cocktails', 'qbm'),
                'edit_item'                  => esc_html__('Edit Cocktail', 'qbm'),
                'view_item'                  => esc_html__('View Cocktail', 'qbm'),
                'update_item'                => esc_html__('Update Cocktail', 'qbm'),
                'add_new_item'               => esc_html__('Add new Cocktail', 'qbm'),
                'new_item'                   => esc_html__('New Cocktail', 'qbm'),
                'parent_item'                => esc_html__('Parent Cocktail', 'qbm'),
                'parent_item_colon'          => esc_html__('Parent Cocktail', 'qbm'),
                'search_items'               => esc_html__('Search Cocktails', 'qbm'),
                'popular_items'              => esc_html__('Popular Cocktails', 'qbm'),
                'separate_items_with_commas' => esc_html__('Separate Cocktails with commas', 'qbm'),
                'add_or_remove_items'        => esc_html__('Add or remove Cocktails', 'qbm'),
                'choose_from_most_used'      => esc_html__('Choose most used Cocktails', 'qbm'),
                'not_found'                  => esc_html__('No cocktails found', 'qbm'),
                'name'                       => esc_html__('Cocktails', 'qbm'),
                'singular_name'              => esc_html__('Cocktail', 'qbm'),
            ],
            'public'              => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_nav_menus'   => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => true,
            'rest_base'           => 'cocktails',
            'menu_position'       => 20,
            'menu_icon'           => 'dashicons-coffee',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title', 'custom-fields', 'thumbnail'],
            'has_archive'         => true,
            'rewrite'             => [
                'slug' => 'cocktails',
                'with_front' => false,
            ],
            'query_var'           => true,
        ]);

        // add_action('pre_get_posts', [$this, 'randomizeArchives']);
        // add_action('set_object_terms', [$this, 'preventAddingTaxonomiesWhileEditing']);
        add_action('pre_get_posts', [$this, 'handleQueryParams']);
        add_action('pre_get_posts', [$this, 'handleCustomSearchPhrase']);
        add_action('do_meta_boxes', [$this, 'renameFeaturedImageMetabox']);
        add_filter('admin_post_thumbnail_html', [$this, 'renameFeaturedImageLinks']);
    }

    // public function randomizeArchives($query) {
    //     // Randomize on initial page load
    //     if (is_post_type_archive(self::POST_TYPE)) {
    //         set_query_var('orderby', 'rand');
    //     }
    // }

    // public function preventAddingTaxonomiesWhileEditing(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids) {
    //     error_log(print_r($object_id, true));
    //     error_log(print_r($terms, true));
    //     error_log(print_r($tt_ids, true));
    //     throw new \Exception('HALT');
    // }

    public function handleQueryParams($query) {
        if ($query->is_main_query() && !is_admin() && $query->query['post_type'] === self::POST_TYPE && is_post_type_archive(self::POST_TYPE)) {
            $query->set('posts_per_page', 12);

            $taxQuery = [];

            // Spirits
            $spirits = $this->parse_query_param_into_array($_GET['spirits']);
            if ( ! empty($spirits)) {
                $spiritsTaxQuery = [];

                if (in_array('other', $spirits)) {
                    // Query all cocktails with spirits OTHER than common spirit filters
                    $spiritsTaxQuery[] = [
                        'taxonomy' => Taxonomies\Spirit::TAXONOMY_TYPE,
                        'field' => 'term_id',
                        'terms' => [
                            // TODO: Don't hardcode these
                            20, // Rum
                            4, // Vodka
                            21, // Gin
                            22, // Bourbon
                            14, // Whiskey
                            23, // Scotch
                            12, // Tequila
                            24, // Brandy
                        ],
                        'operator'  => 'NOT IN',
                        'include_children' => false
                    ];
                }

                $spiritIds = array_filter($spirits, function($val) { return $val !== 'other'; });
                if ( ! empty($spiritIds)) {
                    // Query all cocktails with matching selected spirit filters
                    $spiritsTaxQuery[] = [
                        'taxonomy' => Taxonomies\Spirit::TAXONOMY_TYPE,
                        'field' => 'term_id',
                        'terms' => $spiritIds,
                        'include_children' => false
                    ];
                }

                if (count($spiritsTaxQuery) > 1) {
                    // Use "OR" matching if both "other" and specific spirit filters are selected
                    $spiritsTaxQuery['relation'] = 'OR';
                }

                $taxQuery[] = $spiritsTaxQuery;

                $query->set('qbm-selected-spirits', $spirits);
            }

            // Attributes
            $attrs = $this->parse_query_param_into_array($_GET['attrs']);
            if ( ! empty($attrs)) {
                $taxQuery[] = [
                    'taxonomy' => Taxonomies\Attribute::TAXONOMY_TYPE,
                    'field' => 'term_id',
                    'terms' => $attrs,
                    'include_children' => false
                ];
                $query->set('qbm-selected-attrs', $attrs);
            }

            // Ingredients
            $ingredients = $this->parse_query_param_into_array($_GET['ingredients']);
            if ( ! empty($ingredients)) {
                $taxQuery[] = [
                    'taxonomy' => Taxonomies\Ingredient::TAXONOMY_TYPE,
                    'field' => 'term_id',
                    'terms' => $ingredients,
                    'include_children' => false
                ];
                $query->set('qbm-selected-ingredients', $ingredients);
            }

            // Collections
            $collections = $this->parse_query_param_into_array($_GET['collections']);
            if ( ! empty($collections)) {
                if (in_array('newest', $collections)) {
                    set_query_var('orderby', 'date');
                    set_query_var('order', 'desc');
                } else {
                    $taxQuery[] = [
                        'taxonomy' => Taxonomies\Collection::TAXONOMY_TYPE,
                        'field' => 'term_id',
                        'terms' => $collections,
                        'include_children' => false
                    ];
                }
                $query->set('qbm-selected-collections', $collections);
            }

            if ( ! empty($taxQuery)) {
                $query->tax_query->queries[] = $taxQuery;
                $query->query_vars['tax_query'] = $query->tax_query->queries;
            }
        }
    }

    public function handleCustomSearchPhrase($query) {
        if ($query->query['post_type'] === self::POST_TYPE) {
            if ($phrase = $query->get('s')) {
                $query->set('s', false);
                // TODO: Maybe don't clobber meta_query in case it's needed for extra conditions
                $query->set('meta_query', [
                    [
                        'key' => 'search_terms',
                        'value' => $phrase,
                        'compare' => 'LIKE',
                    ],
                ]);
                add_filter('get_meta_sql', function($sql) use ($phrase) {
                    global $wpdb;

                    // Only run this filter once
                    static $nr = 0;
                    if (0 != $nr++) return $sql;

                    // Modify WHERE clause
                    $sql['where'] = sprintf(
                        " AND ( %s OR %s ) ",
                        $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", ["%{$wpdb->esc_like($phrase)}%"]),
                        mb_substr($sql['where'], 5, mb_strlen($sql['where'])),
                    );

                    return $sql;
                });
            }
        }
    }

    public function renameFeaturedImageMetabox() {
        remove_meta_box('postimagediv', self::POST_TYPE, 'side');
        add_meta_box('postimagediv', __('Thumbnail'), 'post_thumbnail_meta_box', self::POST_TYPE, 'normal', 'high');
    }

    public function renameFeaturedImageLinks($content) {
        global $post;
        if ($post->post_type == self::POST_TYPE) {
            $content = str_replace(__('Set featured image'), __('Set thumbnail'), $content);
            $content = str_replace(__('Remove featured image'), __('Remove thumbnail'), $content);
        }
        return $content;
    }

    private function parse_query_param_into_array($param) {
        return array_map('intval', ( ! is_array($param ?? ''))
            ? ($param ? [$param] : [])
            : $param);
    }
}
