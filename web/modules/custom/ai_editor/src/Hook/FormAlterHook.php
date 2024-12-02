<?php

namespace Drupal\ai_editor\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

class FormAlterHook {
  protected $entityTypeManager;
  protected $configFactory;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  public function alterForm(&$form, FormStateInterface $form_state, $form_id) {
    // Додаткова логіка налаштування форми
    // Можна додати більш складну маніпуляцію з налаштуваннями AI
  }
}
