<?php

namespace Drupal\editoria11y\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\views\Plugin\views\field\Standard;
use Drupal\views\ResultRow;

/**
 * Render a value to the page.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("editoria11y_page_link")
 */
class PageLink extends Standard {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = parent::render($values);

    if (!empty($value)) {
      $path = $values->editoria11y_results_page_path ?? $values->editoria11y_dismissals_page_path;

      // @phpstan-ignore-next-line
      $config = \Drupal::config('editoria11y.settings');
      $prefix = $config->get('redundant_prefix');
      if (!empty($prefix)) {
        // Replace first instance.
        $pos = strpos($path, $prefix);
        if ($pos !== FALSE) {
          $path = substr_replace($path, "", $pos, strlen($prefix));
        }
      }

      // @phpstan-ignore-next-line (Why have services if you don't use them)
      $url = \Drupal::service('path.validator')->getUrlIfValidWithoutAccessCheck($path);
      if (!$url) {
        return $value . ' ' . t('(invalid URL)');
      }

      $url->mergeOptions(['query' => ['ed1ref' => $path]]);
      $value = Link::fromTextAndUrl($value, $url)->toString();

    }

    return $value;
  }

}
