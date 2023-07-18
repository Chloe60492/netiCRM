<?php

/**
 * This class contains all the AI function that are called by AJAX
 */
class CRM_AI_Page_AJAX {

  function chat() {
    $maxlength = 2000;
    $tone_style = $ai_role = $context = null;
    $data = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if ($jsondata === NULL) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request is not a valid JSON format.',
        ));
      }
      $allowedInput = array(
        'tone' => 'string',
        'role' => 'string',
        'content' => 'string',
        'sourceUrlPath' => 'string',
      );
      $checkFormatResult = self::validateJsonData($jsondata, $allowedInput);
      if (!$checkFormatResult) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request does not match the expected format.',
        ));
      }

      $tone_style = $jsondata['tone'];
      $data['tone_style'] = $tone_style;

      $ai_role = $jsondata['role'];
      $data['ai_role'] = $ai_role;

      $context = $jsondata['content'];
      $contextCount = mb_strlen($context);

      if ($contextCount > $maxlength) {
        self::responseError(array(
          'status' => 0,
          'message' => "Content exceeds the maximum character limit.",
        ));
      }
      $data['context'] = $context;

      // get url and check component
      $mailTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'Email', 'name');
      $url = $jsondata['sourceUrlPath'];

      $allowPatterns = [
        'CiviContribute' => ['civicrm/admin/contribute/setting'],
        'CiviEvent' => ['civicrm/event/manage/eventInfo'],
        'CiviMail' => ['civicrm/mailing/send'],
        'Activity' => ['civicrm/activity/add', 'civicrm/contact/view/activity'],
      ];

      foreach ($allowPatterns as $component => $allowedUrls) {
        foreach ($allowedUrls as $allowedUrl) {
          if (strstr($url, $allowedUrl)) {
            if ($component === "Activity" && strstr($jsondata['sourceUrl'], "atype=$mailTypeId")) {
              $data['component'] = $component;
              break 2;
            } elseif ($component !== "Activity") {
              $data['component'] = $component;
              break 2;
            }
          }
        }
      }
      if (empty($data['component'])) {
        self::responseError(array(
          'status' => 0,
          'message' => "No corresponding component was found.",
        ));
      }

      if ($tone_style && $ai_role && $context && $data['component']) {
        $system_prompt = ts("You are an %1 in Taiwan who uses Traditional Chinese and is skilled at writing %2 copywriting.",
          array(1 => $ai_role, 2 => $tone_style,)
        );
        $data['prompt'] = array(
          array(
            'role' => 'system',
            'content' => $system_prompt,
          ),
          array(
            'role' => 'user',
            'content' => $context,
          ),
        );
        try {
          $token = CRM_AI_BAO_AICompletion::prepareChat($data);
        }
        catch(CRM_Core_Exception $e) {
          $message = $e->getMessage();
          self::responseError(array(
            'status' => 0,
            'message' => $message,
          ));
        }

        if (is_numeric($token['id']) && is_string($token['token'])) {
          self::responseSucess(array(
            'status' => 1,
            'message' => 'Chat created successfully.',
            'data' => array(
              'id' => $token['id'],
              'token' => $token['token'],
            )
          ));
        }
      }
    }
    // When request method is get,Use stream to return ai content
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      if (isset($_GET['token']) && isset($_GET['id']) && is_string($_GET['token']) && is_string($_GET['id'])) {
        $token = $_GET['token'];
        $id = $_GET['id'];
        $params = array(
          'token' => $token,
          'id' => $id,
          'stream' => TRUE,
          'temperature' => CRM_AI_BAO_AICompletion::TEMPERATURE_DEFAULT,
        );
        try{
          $result = CRM_AI_BAO_AICompletion::chat($params);
        }
        catch(CRM_Core_Exception $e) {
          $message = $e->getMessage();
          self::responseError(array(
            'status' => 0,
            'message' => $message,
          ));
        }
        self::responseSucess(array(
          'status' => 1,
          'message' => 'Stream chat successfully.',
          'data' => $result,
        ));
      }
    }
  }

  function getTemplateList() {
    $data = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if ($jsondata === NULL) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request is not a valid JSON format.',
        ));
      }
      if (isset($jsondata['component']) && is_string($jsondata['component'])) {
        $component = $jsondata['component'];
        $data['component'] = $component;
      }
      if (isset($jsondata['field']) && is_string($jsondata['field'])) {
        $field = $jsondata['field'];
        $data['field'] = $field;
      }
      if (isset($jsondata['offset']) && is_numeric($jsondata['offset'])) {
        $offset = $jsondata['offset'];
        $data['offset'] = $offset;
      }

      if (!empty($data)) {
        $getListResult = CRM_AI_BAO_AICompletion::getTemplateList($data);
      }
      else {
        //Get all template list
        $getListResult = CRM_AI_BAO_AICompletion::getTemplateList();
      }

      if (is_array($getListResult) && !empty($getListResult)) {
        self::responseSucess(array(
          'status' => 1,
          'message' => "Template list retrieved successfully.",
          'data' => $getListResult,
        ));
      }
      else {
        self::responseError(array(
          'status' => 0,
          'message' => "Failed to retrieve template list.",
        ));
      }
    }
  }

  function getTemplate() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if ($jsondata === NULL) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request is not a valid JSON format.',
        ));
      }
      if (isset($jsondata['id']) && is_numeric($jsondata['id'])) {
        $acId = $jsondata['id'];
      }
      if ($acId) {
        $getTemplateResult = CRM_AI_BAO_AICompletion::getTemplate($acId);
        if (is_array($getTemplateResult) && !empty($getTemplateResult)) {
          self::responseSucess(array(
            'status' => 1,
            'message' => "Template retrieved successfully.",
            'data' => $getTemplateResult,
          ));
        }
        else {
          self::responseError(array(
            'status' => 0,
            'message' => "Failed to retrieve template.",
          ));
        }
      }
    }
  }

  function setTemplate() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if ($jsondata === NULL) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request is not a valid JSON format.',
        ));
      }
      $allowedInput = array(
        'id' => 'integer',
        'is_template' => 'integer',
        'template_title' => 'string',
      );
      $checkFormatResult = self::validateJsonData($jsondata, $allowedInput);
      if (!$checkFormatResult) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request does not match the expected format.',
        ));
      }
      $acId = $jsondata['id'];
      $data['id'] = $acId;

      $acIsTemplate = $jsondata['is_template'];
      $data['is_template'] = $acIsTemplate;

      $acTemplateTitle = $jsondata['template_title'];
      $data['template_title'] = $acTemplateTitle;

      if (!empty($acId) && !empty($acIsTemplate) && !empty($acTemplateTitle)) {
        $result = array();
        $setTemplateResult = CRM_AI_BAO_AICompletion::setTemplate($data);
        if ($setTemplateResult['is_error'] === 0) {
          //set or unset template successful return true
          if ($acIsTemplate == "1") {
            //0 -> 1
            $result = array(
              'status' => 1,
              'message' => "AI completion is set as template successfully.",
              'data' => array(
                'id' => $setTemplateResult['id'],
                'is_template' => $setTemplateResult['is_template'],
                'template_title' => $setTemplateResult['template_title'],
              ),
            );
          }
          else {
            //  1 -> 0
            $result = array(
              'status' => 1,
              'message' => "AI completion is unset as template successfully",
              'data' => array(
                'id' => $setTemplateResult['id'],
                'is_template' => $setTemplateResult['is_template'],
                'template_title' => $setTemplateResult['template_title'],
              ),
            );
          }
          self::responseSucess($result);
        }
        else {
          //If it cannot be set/unset throw Error
          $result = array(
            'status' => 0,
            'message' => $setTemplateResult['message'],
            'data' => array(
              'id' => $setTemplateResult['id'],
              'is_template' => $setTemplateResult['is_template'],
              'template_title' => $setTemplateResult['template_title'],
            ),
          );
          self::responseError($result);
        }
      }
    }
  }

  function setShare() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
      $jsonString = file_get_contents('php://input');
      $jsondata = json_decode($jsonString, true);
      if ($jsondata === NULL) {
        self::responseError(array(
          'status' => 0,
          'message' => 'The request is not a valid JSON format.',
        ));
      }
      if (isset($jsondata['id']) && is_numeric($jsondata['id'])) {
        $acId = $jsondata['id'];
      }
      if (isset($jsondata['is_share_with_others']) && is_numeric($jsondata['is_share_with_others'])) {
        $acIsShare = $jsondata['is_share_with_others'];
      }
      if (isset($acId) && isset($acIsShare)) {
        $setShareResult = CRM_AI_BAO_AICompletion::setShare($acId);
        $result = array();
        if ($setShareResult) {
          self::responseSucess(array(
            'status' => 1,
            'message' => "AI completion is set as shareable successfully.",
            'data' => array(
              'id' => $acId,
              'is_template' => $acIsShare,
            ),
          ));
        }
        else {
          self::responseError(array(
            'status' => 0,
            'message' => 'AI completion has already been set as shareable.',
          ));
        }
      }
    }
  }

  /**
   * This function handles the response in case of an error.
   *
   * @param mixed $error The error message or object that needs to be sent as a response.
   */
  function responseError($error) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($error);
    CRM_Utils_System::civiExit();
  }

  /**
   * This function handles the response in case of success.
   *
   * @param mixed $data The data that needs to be sent as a response.
   */
  public static function responseSucess($data) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    CRM_Utils_System::civiExit();
  }

  /**
   * Function to validate JSON data.
   *
   * This function iterates over the allowed inputs and checks if these inputs exist in the JSON data,
   * and if the type of these inputs matches the expected type. If all inputs exist and are of the correct type,
   * the function will return true; otherwise, it will return false.
   *
   * @param array $jsondata The JSON data to be validated.
   * @param array $allowedInput An associative array where the keys are what we expect to find in the JSON data,
   *                            and the values are the types that these inputs should have.
   * @return bool Returns true if all inputs exist and are of the correct type; otherwise returns false.
   */
  public static function validateJsonData($jsondata, $allowedInput) {
    foreach ($allowedInput as $key => $type) {
      if (!isset($jsondata[$key])) {
        return false;
      }
      if ($type === 'integer' || $type === 'double') {
        if (!is_numeric($jsondata[$key])) {
          return false;
        }
      } else if (gettype($jsondata[$key]) != $type) {
        return false;
      }
    }
    return true;
  }
}