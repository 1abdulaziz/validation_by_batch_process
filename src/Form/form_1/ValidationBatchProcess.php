<?php

namespace Drupal\validation_by_batch_process\Form\form_1;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a validation by batch process form.
 */
class ValidationBatchProcess extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'validation_by_batch_process_validation_batch_process';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // file upload
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#required' => TRUE,
      '#upload_location' => 'public://',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    // submit button
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    // here validation logic
    $file = $form_state->getValue('file');
    $validateCsvFile = validateCsvFile($file[0],
      [
        'headers' => [
          'Name',
          'ID'
        ],
      ]
    );
    if (!$validateCsvFile->pass) {
      $form_state->setErrorByName('file', $validateCsvFile->errors);
    }else{
      $form_state->setValue('data', $validateCsvFile->rows);
      $form_state->setValue('fid', $file[0]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // here validation by batch api process
    $data = $form_state->getValue('data');
    // validation by batch api process
    $batch_builder = (new BatchBuilder())
      ->setTitle(t('Processing Batch'))
      ->setFinishCallback([$this, 'finishProcess'])
      ->setInitMessage(t('Batch is starting'))
      ->setProgressMessage(t('Processed @current out of @total.'))
      ->setErrorMessage(t('Batch has encountered an error'));
    foreach ($data as $row) {
      $batch_builder->addOperation([$this, 'validate'], [
        $row,
        $form_state->getValue('fid'),
      ]);
    }
    batch_set($batch_builder->toArray());
  }

    /**
     * {@inheritdoc}
     */
  public function validate($row,$fid, &$context) {
    // here validation logic
    if (mb_strlen($row[0]) < 10) {
      $context['results']['error'][] = $row[0];
    }
    else {
      $context['results']['success'][] = $row[0];
    }
    // pass fid to finishProcess function
    $context['results']['fid'] = $fid;
    \Drupal::logger('validation_by_batch_process')->info('@row : @row1', ['@row' => $row[0], '@row1' => $row[1]]);
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
    // redirect to front page
    \Drupal::service('page_cache_kill_switch')->trigger();
    // redirect to preview page with pass array and fail array.
    $url = Url::fromRoute('validation_by_batch_process.preview_after_validation');
    // pass data by Post method.
    $url->setOption('query', [
      'fid' => $results['fid'],
    ]);
    $response = new RedirectResponse($url->toString());
    $response->send();

    return;

  }
}
