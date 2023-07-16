<?php

namespace Drupal\validation_by_batch_process\Form\form_1;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a validation by batch process form.
 */
class PreviewAfterValidation extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'validation_by_batch_process_preview_after_validation';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // get from session or redirect
    $fid = \Drupal::request()->query->get('fid');
    if (!$fid || !is_numeric($fid)) {
      $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
      $response->send();
      \Drupal::messenger()->addWarning('File not found.');
      return;
    }
    $loaded_file = File::load($fid);
    if (!$loaded_file) {
      $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
      $response->send();
      \Drupal::messenger()->addWarning('File not found.');
      return;
    }
    $read_file = readCsvFile($fid);
    if (!$read_file->pass) {
      $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
      $response->send();
      \Drupal::messenger()->addWarning('File not found.');
      return;
    }
    // save array on variable form state.
    $form_state->set('data', $read_file);
  // preview table
    $form['table'] = [
      '#type' => 'table',
      '#header' => $read_file->rows[0],
    ];

    foreach ($read_file->rows as $row) {
      // skip header
      if ($row[0] == 'Name') {
        continue;
      }
      $form['table'][] = [
        'name' => [
          '#plain_text' => $row[0],
        ],
        'id' => [
          '#plain_text' => $row[1],
        ],
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // here validation by batch api process
    $data = $form_state->get('data');
    // validation by batch api process
    $batch_builder = (new BatchBuilder())
      ->setTitle(t('Processing Batch'))
      ->setFinishCallback([$this, 'finishProcess'])
      ->setInitMessage(t('Batch is starting'))
      ->setProgressMessage(t('Processed @current out of @total.'))
      ->setErrorMessage(t('Batch has encountered an error'));
    foreach ($data->rows as $row) {
      $batch_builder->addOperation([$this, 'process'], [
        $row,
      ]);
    }
    batch_set($batch_builder->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function process($row, &$context) {
    // import your data.

    sleep(5);

  }


  /**
   * Batch process finished callback.
   */
  public function finishProcess($success, $results, array $operations)
  {
    // Do something when processing is finished.
    if ($success) {
      \Drupal::messenger()->addMessage('Batch processing completed.');
      \Drupal::logger('validation_by_batch_process')->info('Batch processing completed.');
    }
    if (!empty($operations)) {
      \Drupal::messenger()->addMessage('Batch processing failed: ' . implode(', ', $operations) . '.');
      \Drupal::logger('validation_by_batch_process')->error('Batch processing failed: ' . implode(', ', $operations));
    }
  }

}
