<?php

namespace Drupal\ai_automators\FormAlter;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_automators\AiFieldRules;
use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

/**
 * A helper to store configs for fields.
 */
class AiAutomatorFieldConfig {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The processes available.
   */
  protected AiAutomatorFieldProcessManager $processes;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a field config modifier.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_automators\AiFieldRules $fieldRules
   *   The field rule manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match interface.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   The module handler.
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager $processes
   *   The process manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityFieldManagerInterface $fieldManager, AiFieldRules $fieldRules, RouteMatchInterface $routeMatch, ModuleHandlerInterface $moduleHandler, AiAutomatorFieldProcessManager $processes, EntityTypeManagerInterface $entityTypeManager) {
    $this->fieldManager = $fieldManager;
    $this->fieldRules = $fieldRules;
    $this->routeMatch = $routeMatch;
    $this->moduleHandler = $moduleHandler;
    $this->processes = $processes;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Alter the form with field config if applicable.
   *
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function alterForm(array &$form, FormStateInterface $formState, $field_name = 'automator') {

    // Get the entity and the field name.
    $entity = $form['#entity'];

    // Try different ways to get the field name.
    $fieldName = NULL;
    $routeParameters = $this->routeMatch->getParameters()->all();
    if (!empty($routeParameters['field_name'])) {
      $fieldName = $routeParameters['field_name'];
    }
    elseif (!empty($routeParameters['field_config'])) {
      $fieldName = $routeParameters['field_config']->getName();
    }
    elseif (!empty($routeParameters['base_field_override'])) {
      $fieldName = $routeParameters['base_field_override']->getName();
    }

    // If no field name it is not for us.
    if (!$fieldName) {
      return;
    }

    // Get the field config.
    $fields = $this->fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    /** @var \Drupal\field\Entity\FieldConfig */
    $fieldInfo = $fields[$fieldName] ?? NULL;

    // Try to get it from the form session if not existing.
    if (!$fieldInfo) {
      /** @var \Drupal\Core\Entity\ConfigEntityForm $formObject */
      $formObject = $formState->getFormObject();
      $fieldInfo = $formObject->getEntity();
    }

    // The info might not have been saved yet.
    if (!$fieldInfo) {
      return;
    }

    // Find the rules. If not found don't do anything.
    $rules = $this->fieldRules->findRuleCandidates($entity, $fieldInfo);

    if (empty($rules)) {
      return;
    }

    // Generate unique keys using the $field_name parameter.
    $enabled_key = "{$field_name}_enabled";
    $rule_key = "{$field_name}_rule";
    $container_key = "{$field_name}_container";

    // Get the default config if it exists.
    $id = $form['#entity']->getEntityTypeId() . '.' . $form['#entity']->bundle() . '.' . $fieldInfo->getName() . '.default';

    /** @var \Drupal\ai_automators\Entity\AiAutomator $aiConfig */
    $aiConfig = $this->entityTypeManager->getStorage('ai_automator')->load($id);

    $form[$enabled_key] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable AI Automator'),
      '#description' => $this->t('If you want this value to be auto filled from AI'),
      '#weight' => 15,
      '#default_value' => !is_null($aiConfig),
      '#attributes' => [
        'name' => "{$field_name}_enabled",
      ],
    ];

    $rulesOptions = [];
    foreach ($rules as $ruleKey => $rule) {
      $rulesOptions[$ruleKey] = $rule->title;
    }

    $chosenRule = $formState->getValue($rule_key) ?? NULL;
    if (empty($chosenRule) && !is_null($aiConfig)) {
      $chosenRule = $aiConfig->get('rule');
    }
    $chosenRule = $chosenRule ? $chosenRule : key($rulesOptions);
    $rule = $rules[$chosenRule] ?? $rules[key($rulesOptions)];

    $form[$rule_key] = [
      '#type' => 'select',
      '#title' => $this->t('Choose AI Automator Type'),
      '#description' => $this->t('Some field type might have many types to use, based on the modules you installed'),
      '#weight' => 16,
      '#options' => $rulesOptions,
      '#default_value' => $chosenRule,
      '#states' => [
        'visible' => [
          "input[name=\"{$field_name}_enabled\"]" => [
            'checked' => TRUE,
          ],
        ],
      ],
      // Update dynamically.
      '#ajax' => [
        'callback' => [$this, 'updateRule'],
        'event' => 'change',
        'wrapper' => "$field_name".'-container',
      ],
    ];

    // Show help text.
    if ($rule->helpText()) {
      $form["$field_name".'_help_text'] = [
        '#type' => 'details',
        '#title' => $this->t('About this rule'),
        '#weight' => 17,
        '#states' => [
          'visible' => [
            "input[name=\"{$field_name}_enabled\"]" => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      $form['automator_help_text']['help_text'] = [
        '#markup' => $rule->helpText(),
      ];
    }

    $form[$container_key] = [
      '#type' => 'details',
      '#title' => $this->t('AI Automator Settings'),
      '#weight' => 18,
      '#open' => TRUE,
      '#attributes' => [
        'id' => [
          $field_name.'-container',
        ],
      ],
      '#states' => [
        'visible' => [
          "input[name=\"{$field_name}_enabled\"]" => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $defaultValues = !is_null($aiConfig) ? $aiConfig->get('plugin_config') : [];
    $subForm = $rule->extraFormFields($entity, $fieldInfo, $formState, $defaultValues);
    $form[$container_key] = array_merge($form[$container_key], $subForm);

    $modeOptions['base'] = $this->t('Base Mode');
    // Not every rule allows advanced mode.
    if ($rule->advancedMode()) {
      $modeOptions['token'] = $this->t('Advanced Mode (Token)');
    }

    if ($this->moduleHandler->moduleExists('token')) {
      $description = $rule->advancedMode() ? $this->t('The Advanced Mode (Token) is available for this Automator Type to use multiple fields as input, you may also choose Base Mode to choose one base field.') :
        $this->t('For this Automator Type, only the Base Mode is available. It uses the base field to generate the content.');
      $form[$container_key]["$field_name".'_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Automator Input Mode'),
        '#description' => $description,
        '#options' => $modeOptions,
        '#default_value' => !is_null($aiConfig) ? $aiConfig->get('input_mode') : 'base',
        '#weight' => 5,
        '#attributes' => [
          'name' => "$field_name".'_mode',
        ],
      ];
    }
    else {
      $form[$container_key]["$field_name".'_mode'] = [
        '#value' => 'base',
      ];
    }

    // Prompt with token.
    $form[$container_key]['normal_prompt'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#weight' => 11,
      '#states' => [
        'visible' => [
          "input[name=\"{$field_name}_mode\"]" => [
            'value' => 'base',
          ],
        ],
      ],
    ];
    // Create Options for base field.
    $baseFieldOptions = [];
    foreach ($fields as $fieldId => $fieldData) {
      if (in_array($fieldData->getType(), $rule->allowedInputs()) && $fieldId != $fieldName) {
        $baseFieldOptions[$fieldId] = $fieldData->getLabel();
      }
    }

    $form[$container_key]['normal_prompt']["$field_name".'_base_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Automator Base Field'),
      '#description' => $this->t('This is the field that will be used as context field for generating data into this field.'),
      '#options' => $baseFieldOptions,
      '#default_value' => !is_null($aiConfig) ? $aiConfig->get('base_field') : NULL,
      '#weight' => 5,
    ];

    // Prompt if needed.
    if ($rule->needsPrompt()) {
      $form[$container_key]['normal_prompt']["$field_name".'_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Automator Prompt'),
        '#description' => $this->t('The prompt to use to fill this field.'),
        '#attributes' => [
          'placeholder' => $rule->placeholderText(),
        ],
        '#default_value' => !is_null($aiConfig) ? $aiConfig->get('prompt') : NULL,
        '#weight' => 10,
      ];

      // Placeholders available.
      $form[$container_key]['normal_prompt']["$field_name".'_prompt_placeholders'] = [
        '#type' => 'details',
        '#title' => $this->t('Placeholders available'),
        '#weight' => 15,
      ];

      $placeholderText = "";
      foreach ($rule->tokens($entity) as $key => $text) {
        $placeholderText .= "<strong>{{ $key }}</strong> - " . $text . "<br>";
      }
      $form[$container_key]['normal_prompt']["$field_name".'_prompt_placeholders']['placeholders'] = [
        '#markup' => $placeholderText,
      ];
    }
    else {
      // Just save empty.
      $form[$field_name.'_prompt'] = [
        '#value' => '',
      ];
    }
    if ($rule->advancedMode()) {
      // Prompt with token.
      $form[$container_key]['token_prompt'] = [
        '#type' => 'fieldset',
        '#open' => TRUE,
        '#weight' => 11,
        '#states' => [
          'visible' => [
            ':input[name="automator_mode"]' => [
              'value' => 'token',
            ],
          ],
        ],
      ];

      // Tokens help - static service call since module might not exist.
      if ($this->moduleHandler->moduleExists('token')) {
        $form[$container_key]['token_prompt']["$field_name".'_token'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Automator Prompt (Token)'),
          '#description' => $this->t('The prompt to use to fill this field.'),
          '#default_value' => !is_null($aiConfig) ? $aiConfig->get('token') : NULL,
        ];

        // Because we have to invoke this only if the module is installed, no
        // dependency injection.
        // @codingStandardsIgnoreLine @phpstan-ignore-next-line
        $form[$container_key]['token_prompt']['token_help'] = \Drupal::service('token.tree_builder')->buildRenderable([
          $this->getEntityTokenType($entity->getEntityTypeId()),
          'current-user',
        ]);
      }
    }

    $form[$container_key]["$field_name".'_edit_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Edit when changed'),
      '#description' => $this->t('By default the initial value or manual set value will not be overriden. If you check this, it will override if the base text field changes its value.'),
      '#default_value' => !is_null($aiConfig) ? $aiConfig->get('edit_mode') : FALSE,
      '#weight' => 20,
    ];

    $form[$container_key]["$field_name".'_advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#weight' => 25,
    ];

    $form[$container_key]["$field_name".'_advanced']['label_detail'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Automator Label'),
    ];

    $form[$container_key]["$field_name".'_advanced']['label_detail']['automator_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Automator Label'),
      '#description' => $this->t('The label of the automator for referencing.'),
      '#default_value' => !is_null($aiConfig) ? $aiConfig->get('label') : $fieldInfo->getLabel() . ' Default',
    ];

    $form[$container_key]["$field_name".'_advanced']["$field_name".'_weight'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => 1000,
      '#title' => $this->t('Automator Weight'),
      '#description' => $this->t('If you have fields dependent on each other, you can sequentially order the processing using weights. The higher the value, the later it is run.'),
      '#default_value' => !is_null($aiConfig) ? $aiConfig->get('weight') : 100,
    ];

    // Get possible processes.
    $workerOptions = [];
    foreach ($this->processes->getDefinitions() as $definition) {
      // Check so the processor is allowed.
      $instance = $this->processes->createInstance($definition['id']);
      if ($instance->processorIsAllowed($entity, $fieldInfo)) {
        $workerOptions[$definition['id']] = $definition['title'] . ' - ' . $definition['description'];
      }
    }

    $form[$container_key]["$field_name".'_advanced']["$field_name".'_worker_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Automator Worker'),
      '#options' => $workerOptions,
      '#description' => $this->t('This defines how the saving of an interpolation happens. Direct saving is the easiest, but since it can take time you need to have longer timeouts.'),
      '#default_value' => !is_null($aiConfig) ? $aiConfig->get('worker_type') : 'direct',
    ];

    $subForm = $rule->extraAdvancedFormFields($entity, $fieldInfo, $formState, $defaultValues);
    $form[$container_key]["$field_name".'_advanced'] = array_merge($form[$container_key]["$field_name".'_advanced'], $subForm);

    // Validate.
    $form['#validate'][] = [$this, 'validateConfigValues'];
    // Save.
    $form['#entity_builders'][] = [$this, 'addConfigValues'];

    // dump($form);
  }

  /**
   * Updates the config form with the chosen rule.
   *
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function updateRule(array &$form, FormStateInterface $formState, $field_name ='automator') {
    return $form["$field_name".'_container'];
  }

  /**
   * Validates the field config form.
   *
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function validateConfigValues(&$form, FormStateInterface $formState, $field_name ='automator') {
    if ($formState->getValue("$field_name".'_enabled')) {
      $values = $formState->getValues();
      foreach ($values as $key => $val) {
        if (strpos($key, "$field_name".'_') === 0) {
          // Find the rule. If not found don't do anything.
          $rule = $this->fieldRules->findRule($formState->getValue("$field_name".'_rule'));

          // Validate the configuration.
          if ($rule->needsPrompt() && $formState->getValue("$field_name".'_mode') == 'base' && !$formState->getValue("$field_name".'_prompt')) {
            $formState->setErrorByName("$field_name".'_prompt', $this->t('If you enable AI Automator, you have to give a prompt.'));
          }
          if ($formState->getValue("$field_name".'_mode') == 'base' && !$formState->getValue("$field_name".'_base_field')) {
            $formState->setErrorByName("$field_name".'_base_field', $this->t('If you enable AI Automator, you have to give a base field.'));
          }
          // Run the rule validation.
          if (method_exists($rule, 'validateConfigValues')) {
            $rule->validateConfigValues($form, $formState);
          }
        }
      }
    }

    return TRUE;
  }

  /**
   * Builds the field config.
   *
   * @param string $entity_type
   *   The entity type being used.
   * @param \Drupal\field\Entity\FieldConfig|\Drupal\Core\Field\Entity\BaseFieldOverride|\Drupal\node\Entity\Node $fieldConfig
   *   The field config or a node entity.
   * @param array $form
   *   The form passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state interface.
   */
  public function addConfigValues($entity_type, FieldConfig|BaseFieldOverride|Node $fieldConfig, &$form, FormStateInterface $formState, $field_name = 'automator') {

    // Якщо переданий об'єкт - це вузол (Node).
    if ($fieldConfig instanceof Node) {
      // Логіка для вузла. Наприклад:
      $fieldConfig = $fieldConfig->getFieldDefinitions()['field_ai_text'];
      if (!$fieldConfig instanceof FieldConfig) {
        return FALSE;
      }
    }

    // Продовжуємо виконання для FieldConfig або BaseFieldOverride.
    $id = $form['#entity']->getEntityTypeId() . '.' . $form['#entity']->bundle() . '.' . $fieldConfig->getName() . '.default';

    /** @var \Drupal\ai_automators\Entity\AiAutomator $aiConfig */
    $aiConfig = $this->entityTypeManager->getStorage('ai_automator')->load($id);

    // Save the configuration.
    if ($formState->getValue("$field_name".'_enabled')) {
      if (!$aiConfig) {
        // Create a new one if there is no config.
        /** @var \Drupal\ai_automators\Entity\AiAutomator $aiConfig */
        $aiConfig = $this->entityTypeManager->getStorage('ai_automator')->create([
          'id' => $id,
          'entity_type' => $form['#entity']->getEntityTypeId(),
          'bundle' => $form['#entity']->bundle(),
          'field_name' => $fieldConfig->getName(),
        ]);
      }
      $aiConfig->set('label', $formState->getValue("$field_name".'_label') ?? $fieldConfig->getLabel() . ' Default');
      $aiConfig->set('rule', $formState->getValue("$field_name".'_rule'));
      $aiConfig->set('input_mode', $formState->getValue("$field_name".'_mode') ?? 'base');
      $aiConfig->set('weight', $formState->getValue("$field_name".'_weight'));
      $aiConfig->set('worker_type', $formState->getValue("$field_name".'_worker_type'));
      $aiConfig->set('edit_mode', $formState->getValue("$field_name".'_edit_mode'));
      $aiConfig->set('base_field', $formState->getValue("$field_name".'_base_field'));
      $aiConfig->set('prompt', $formState->getValue("$field_name".'_prompt') ?? '');
      $aiConfig->set('token', $formState->getValue("$field_name".'_token') ?? '');

      $pluginConfig = [];
      foreach ($formState->getValues() as $key => $val) {
        if (substr($key, 0, 10) == "$field_name".'_') {
          $pluginConfig[$key] = $val;
        }
      }
      $aiConfig->set('plugin_config', $pluginConfig);
      $aiConfig->save();
    } elseif ($aiConfig) {
      // Remove it if disabled and exists.
      $aiConfig->delete();
    }
    return TRUE;
  }

  /**
   * Gets the entity token type.
   *
   * @param string $entityTypeId
   *   The entity type id.
   *
   * @return string
   *   The corrected type.
   */
  public function getEntityTokenType($entityTypeId) {
    switch ($entityTypeId) {
      case 'taxonomy_term':
        return 'term';
    }
    return $entityTypeId;
  }

}
