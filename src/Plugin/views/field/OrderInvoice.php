<?php

namespace Drupal\commerce_szamlazz\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("szamlazz_invoice")
 */
class OrderInvoice extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Define the available options.
   *
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $types = NodeType::loadMultiple();
    $options = [];

    foreach ($types as $key => $type) {
      $options[$key] = $type->label();
    }

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $order = $values->_entity;
    if ($order->get('state')->getValue()[0]['value'] == 'completed') {
      $url = Url::fromRoute('commerce_szamlazz.invoice', ['oid' => 1]);
      $project_link = Link::fromTextAndUrl(t('Generate invoice'), $url);
      return $project_link->toRenderable();
      // return;.
    }
    else {
      return ' - ';
    }
  }

}
