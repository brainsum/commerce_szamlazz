<?php

namespace Drupal\commerce_szamlazz\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("szamlazz_invoice")
 */
class OrderInvoice extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   *
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Define the available options.
   *
   * @return array
   *   Returns an array.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * Render function.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $order = $values->_entity;
    if ($order->get('state')->getValue()[0]['value'] == 'completed') {
      $url          = Url::fromRoute('commerce_szamlazz.invoice', ['oid' => 1]);
      $project_link = Link::fromTextAndUrl(t('Generate invoice'), $url);
      return $project_link->toRenderable();
      // return;.
    }
    else {
      return ' - ';
    }
  }

}
