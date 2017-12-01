<?php

/**
 * @file
 */

/**
 * Implements hook_views_data_alter().
 */
function commerce_szamlazz_views_data_alter(array &$data) {
  $data['commerce_order']['szamlazz_invoice'] = [
    'title' => t('Generate invoice'),
    'group' => t('Szamlazz.hu'),
    'field' => [
      'title' => t('Generate invoice'),
      'help' => t('Generates invoice for the given order using commerce_szamlazz.hu api.'),
      'id' => 'szamlazz_invoice',
    ],
  ];
}
