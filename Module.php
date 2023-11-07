<?php

/**
 * GoogleTagManger
 *
 * Includes simple support for Google Tag Manager in Omeka S
 *
 */

namespace GoogleTagManager;

use Laminas\Validator;
use Laminas\Validator\Callback;
use Laminas\Form\Fieldset;
use Omeka\Module\AbstractModule;
use GoogleTagManager\Form\ConfigForm;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\View;
use Laminas\View\ViewEvent;
use Laminas\View\Renderer\PhpRenderer;

class Module extends AbstractModule {

  protected $validator;

  public function getConfig() {
    return include __DIR__ . '/config/module.config.php';
  }

  public function install(ServiceLocatorInterface $serviceLocator){
    $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'install');
  }

  public function uninstall(ServiceLocatorInterface $serviceLocator) {
    $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');

    // Delete site settings
    $api = $serviceLocator->get('Omeka\ApiManager');
    $sites = $api->search('sites', [])->getContent();
    $siteSettings = $serviceLocator->get('Omeka\Settings\Site');

    foreach ($sites as $site) {
      $siteSettings->setTargetId($site->id());
      $siteSettings->delete('googletagmanager_code');
    }
  }

  protected function manageSettings($settings, $process, $key = 'config') {
    $config = require __DIR__ . '/config/module.config.php';
    $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];

    foreach ($defaultSettings as $name => $value) {
      switch ($process) {
        case 'install':
          $settings->set($name, $value);
          break;
        case 'uninstall':
          $settings->delete($name);
          break;
      }
    }
  }

  public function attachListeners(SharedEventManagerInterface $sharedEventManager) {
    // Insert Google Tag Manager tracking code
    $sharedEventManager->attach(
      View::class,
      ViewEvent::EVENT_RESPONSE,
      [$this, 'printScript']
    );
  }

  public function getConfigForm(PhpRenderer $renderer)
  {
      $services = $this->getServiceLocator();
      $config = $services->get('Config');
      $settings = $services->get('Omeka\Settings');
      $form = $services->get('FormElementManager')->get(ConfigForm::class);

      $data = $settings->get('googletagmanager', ['']);

      $form->init();
      $form->setData($data);
      $html = $renderer->formCollection($form);
      return $html;
  }

  public function handleConfigForm(AbstractController $controller) {
    $services = $this->getServiceLocator();
    $config = $services->get('Config');
    $settings = $services->get('Omeka\Settings');
    $form = $services->get('FormElementManager')->get(ConfigForm::class);

    $params = $controller->getRequest()->getPost();

    $form->init();
    $form->setData($params);

    $code = $params['googletagmanager_code'];

    if (preg_match("/GTM\-\w+/", $code) == 0) {
      $controller->messenger()->addErrors(['The format of Google Tag Manager Code is incorrect']); //@translate
      return false;
    }

    if (!$form->isValid()) {
      $controller->messenger()->addErrors([$form->getMessages()]);
      return false;
    }

    $params = $form->getData();
    $settings->set('googletagmanager', $params);
  }

  /**
   * Print script for Google Tag Manager.
   *
   * @param ViewEvent $viewEvent
   */
  public function printScript(ViewEvent $viewEvent) {

    // In case of error or a internal redirection, there may be two calls.
    static $processed;
    if ($processed) {
        return;
    }
    $processed = true;

    $response = $viewEvent->getResponse();
    $content = $response->getContent();

    $settings = $this->getServiceLocator()->get('Omeka\Settings');
    $settings = $settings->get('googletagmanager', '');
    if ($settings != null) {
      $code = $settings['googletagmanager_code'];
      $headCode = $this->formatHeadCode($code);
      $noscript = $this->formatNoscript($code);
    }
    if (!empty($code)) {
      $content = preg_replace('/(<title>)/', $headCode . '${1}', $content);
      $content = preg_replace('/(<body[^>]*>)/', '${1}' . $noscript, $content);
    }

    $response->setContent($content);

  }

  protected function formatHeadCode($code) {
    return  "<!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{$code}');</script>
    <!-- End Google Tag Manager -->";
  }

  protected function formatNoscript($code) {
    return '<!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $code . '"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->';
  }
}
