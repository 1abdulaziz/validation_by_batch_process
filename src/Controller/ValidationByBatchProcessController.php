<?php

namespace Drupal\validation_by_batch_process\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for validation by batch process routes.
 */
class ValidationByBatchProcessController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
