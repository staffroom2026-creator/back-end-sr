<?php


class Users extends Controller
{
  protected $userModel;
  protected $transactionModel;

  protected function requirePinUser()
  {
    return AuthMiddleware::authenticate();
  }

  protected function hasNonEmptyPinValue($value)
  {
    return (is_string($value) || is_int($value)) && trim((string) $value) !== '';
  }

  public function __construct()
  {
    $this->userModel = $this->model('User');
        $this->thriftModel = $this->model("Thrift");
    $this->transactionModel = $this->model('Transaction');
    $this->serverKey  = 'secret_server_key' . date("H");
  }

  public function chats()
  {
    $sentData = $this->getData();

    $userId1 = $sentData['userId1'] ?? null;
    $userId2 = $sentData['userId2'] ?? null;

    if (!$userId1 || !$userId2) {
      ResponseHelper::error('Missing user IDs', 400);
    }

    // Ensure both users exist in users table
    $this->userModel->ensureUserExists($userId1);
    $this->userModel->ensureUserExists($userId2);

    // Get or create chat
    $chat = $this->userModel->getOrCreateChat($userId1, $userId2);

    if ($chat) {
      ResponseHelper::success('success', $chat);
    } else {
      ResponseHelper::error('Failed to get or create chat', 500);
    }
  }


  public function storeUsers()
  {
    $sentData = $this->getData();

    if (!isset($sentData['users']) || !is_array($sentData['users'])) {
      ResponseHelper::error('Invalid or missing users data', 400);
    }

    $result = $this->userModel->saveUsers($sentData['users']);

    if ($result) {
      ResponseHelper::success('Users saved successfully');
    } else {
      ResponseHelper::error('Failed to save users', 500);
    }
  }


  public function chatsunread_count()
  {
    $data = $this->getData();
    $userId = $data['userId'] ?? null;

    if (!$userId) {
      ResponseHelper::error('userId is required', 400);
    }

    $count = $this->userModel->getUnreadMessageCount($userId);

    ResponseHelper::success('Unread count retrieved', ['count' => $count]);
  }


  public function messagesread()
  {
    $data = $this->getData();
    $chatId = $data['chatId'] ?? null;
    $userId = $data['userId'] ?? null;

    if (!$chatId || !$userId) {
      ResponseHelper::error('chatId and userId are required', 400);
    }

    $this->userModel->markMessagesAsRead($chatId, $userId);

    ResponseHelper::success('Messages marked as read');
  }



  public function chatsuser()
  {
    $data = $this->getData();
    $userId = $data['userId'] ?? null;

    if (!$userId) {
      ResponseHelper::error('userId is required', 400);
    }

    $chats = $this->userModel->getChatsByUserId($userId);

    ResponseHelper::success('Chats retrieved', $chats);
  }




  // In User.php controller

  public function messages()
  {
    $data = $this->getData(); // JSON from frontend

    // Call model function to store message
    $success = $this->userModel->savePersonalMessage($data);

    if ($success) {
      ResponseHelper::success('Message saved successfully');
    } else {
      ResponseHelper::error('Failed to save message', 400);
    }
  }

  public function messagesget()
  {
    $data = $this->getData(); // get JSON body
    $chatId = $data['chatId'] ?? null;

    if (!$chatId) {
      ResponseHelper::error('chatId is required', 400);
    }

    $messages = $this->userModel->getMessagesByChatId($chatId);

    ResponseHelper::success('Messages retrieved', $messages);
  }




  public function loginfunc_admin()
  {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $jsonData = $this->getData();
    if (!isset($jsonData['email']) || !isset($jsonData['password'])) {
      ResponseHelper::error('Enter login details', 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      ResponseHelper::error('Invalid server method', 404);
    }

    $email = trim($jsonData['email']);
    $password = trim($jsonData['password']);

    if (empty($email)) ResponseHelper::error('Please enter email', 400); 
    if (empty($password)) ResponseHelper::error('Please enter password', 400);

    if (!$this->userModel->findUserByEmail1($email)) {
       ResponseHelper::error('invalid user login detail', 404, ['email' => $email]);
    }

    $loginDatax = $this->userModel->loginUser24($email);
    $loginStatus = $loginDatax->activationx;
    $postPassword = $password;
    $hash_password = $loginDatax->password;
    $user_id = $loginDatax->user_id;

    if (password_verify($postPassword, $hash_password)) {
      $tokenX = "token" . md5(date("dmyhis") . rand(1222, 89787));
      $this->userModel->updateToken($user_id, $tokenX);

      $loginData = $this->userModel->findLoginByToken($tokenX);
      $userData = $this->userModel->findUserByEmail_det2($loginData->email);
      $accountDetails = $this->userModel->getAccountDetails($userData->account_id, $user_id);

      $loginData = (array)$loginData;
      $loginData = array_filter($loginData, function ($value, $key) {
        return $key !== "nin_no" && $key !== "nin_img" && $key !== "bvn_no";
      }, ARRAY_FILTER_USE_BOTH);

      $loginData['ninVerified'] = !empty($loginData['nin_no']) ? 1 : 0;
      $loginData['nin_imgVerified'] = !empty($loginData['nin_img']) ? 1 : 0;
      $loginData['bvnVerified'] = !empty($loginData['bvn_no']) ? 1 : 0;

      $initData = [
        'loginData' => $loginData,
        'userAccount' => $accountDetails,
      ];

      $datatoken = [
        'user_id' => $user_id,
        'email' => $email,
        'appToken' => $loginData['token'],
        'iss' => 'PAYKING',
        'iat' => time(),
        'exp' => time() + (15 * 60 * 60),
      ];
      $JWT_token = $this->getMyJsonID($datatoken, $this->serverKey);

      setcookie("session_token", $JWT_token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
      ]);

      ResponseHelper::success('success', $initData, 200);

    } else {
      ResponseHelper::error('Invalid password', 404);
    }
  }
  
  
  public function loginfunc()
  {
    if (session_status() == PHP_SESSION_NONE) {
      session_start();
    }

    $jsonData = $this->getData();
    if (!isset($jsonData['email']) || !isset($jsonData['password'])) {
      ResponseHelper::error('Enter login details', 404);
    }

    if (!isset($jsonData['d_token'])) {
      ResponseHelper::error('device token is missing', 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      ResponseHelper::error('Invalid server method', 404);
    }

    $email = trim($jsonData['email']);
    $password = trim($jsonData['password']);
    $deviceToken = trim($jsonData['d_token']);

    if (empty($email)) ResponseHelper::error('Please enter email', 400); 
    if (empty($password)) ResponseHelper::error('Please enter password', 400);

    if (!$this->userModel->findUserByEmail1($email)) {
       ResponseHelper::error('invalid user login detail', 404, ['email' => $email]);
    }

    if (!$this->userModel->verifyDtoken($deviceToken)) {
      ResponseHelper::error('login in with a new device', 400);
    }

    $loginDatax = $this->userModel->loginUser($email, $deviceToken);
    
    // Check if user exists after login attempt
    if (!$loginDatax) {
         ResponseHelper::error('invalid user login detail', 404);
    }

    $postPassword = $password;
    $hash_password = $loginDatax->password;
    $user_id = $loginDatax->user_id;

    if (password_verify($postPassword, $hash_password)) {
      $tokenX = "token" . md5(date("dmyhis") . rand(1222, 89787));
      $this->userModel->updateToken($user_id, $tokenX);

      $loginData = $this->userModel->findLoginByToken($tokenX);
      $userData = $this->userModel->findUserByEmail_det2($loginData->email);
      $accountDetails = $this->userModel->getAccountDetails($userData->account_id, $user_id);

      $loginData = (array)$loginData;
      $loginData = array_filter($loginData, function ($value, $key) {
        return $key !== "nin_no" && $key !== "nin_img" && $key !== "bvn_no";
      }, ARRAY_FILTER_USE_BOTH);
      
      $loginData['ninVerified'] = !empty($loginData['nin_no']) ? 1 : 0;
      $loginData['nin_imgVerified'] = !empty($loginData['nin_img']) ? 1 : 0;
      $loginData['bvnVerified'] = !empty($loginData['bvn_no']) ? 1 : 0;

      
      $datatoken = [
        'user_id' => $user_id,
        'email' => $loginData['email'],
        'appToken' => $loginData['token'],
      ];
      $JWT_token = $this->getMyJsonID($datatoken, $this->serverKey);
      $initData = [
        'access_token' => $JWT_token,
        'loginData' => $loginData,
        'userAccount' => $accountDetails,
      ];

      setcookie("session_token", $JWT_token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
      ]);

      ResponseHelper::success('success', $initData, 200);

    } else {
      ResponseHelper::error('Invalid password', 404);
    }
  }
  
  
  public function loginfuncLock()
  {
    if (session_status() == PHP_SESSION_NONE) {
      session_start();
    }

    $jsonData = $this->getData();
    if (!isset($jsonData['email']) || !isset($jsonData['code'])) {
      ResponseHelper::error('Enter login details', 404);
    }

    if (!isset($jsonData['d_token'])) {
      ResponseHelper::error('device token is missing', 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      ResponseHelper::error('Invalid server method', 404);
    }

    $email = trim($jsonData['email']);
    $password = trim($jsonData['code']);
    $deviceToken = trim($jsonData['d_token']);

    if (empty($email)) ResponseHelper::error('Please enter email', 400);
    if (empty($password)) ResponseHelper::error('Please enter code', 400);

    if (!$this->userModel->findUserByEmail1($email)) {
       ResponseHelper::error('invalid user login detail', 404);
    }

    if (!$this->userModel->verifyDtoken($deviceToken)) {
      ResponseHelper::error('login in with a new device', 400);
    }

    $loginDatax = $this->userModel->loginUser($email, $deviceToken);
    
    if (!$loginDatax) {
         ResponseHelper::error('invalid user login detail', 404);
    }

    $loginStatus = $loginDatax->activationx;
    $postPassword = $password;
    $hash_password = $loginDatax->lock_pin;
    $user_id = $loginDatax->user_id;

    if ($loginStatus < 1) {
      ResponseHelper::error('login status disabled', 404);
    } else if (password_verify($postPassword, $hash_password)) {
      $tokenX = "token" . md5(date("dmyhis") . rand(1222, 89787));
      $this->userModel->updateToken($user_id, $tokenX);

      $loginData = $this->userModel->findLoginByToken($tokenX);
      $userData = $this->userModel->findUserByEmail_det2($loginData->email);
      $accountDetails = $this->userModel->getAccountDetails($userData->account_id, $user_id);
      
      $initData = [
        'loginData' => $loginData,
        'userAccount' => $accountDetails,
      ];

      $datatoken = [
        'user_id' => $user_id,
        'email' => $email,
        'appToken' => $initData['loginData']->token,
      ];
      $JWT_token = $this->getMyJsonID($datatoken, $this->serverKey);

      setcookie("session_token", $JWT_token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
      ]);

      ResponseHelper::success('success', $initData);
    } else {
      ResponseHelper::error('Invalid password', 404);
    }
  }

  public function getUserBalance()
  {
    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }

    $user_id = $userData->user_id;
    $userDatax = $this->userModel->findUserByEmail_det2($userData->email);
    $accountDetails = $this->userModel->getAccountDetails($userDatax->account_id, $user_id);

    if ($accountDetails) {
      ResponseHelper::success('success', $accountDetails);
    } else {
      ResponseHelper::error('faild', 404);
    }
  }

  public function summarize_transactions($transactions)
  {
    //   $transactions = json_encode($transactions);
    $summary = [
      'total_airtime_amount' => 0,
      'total_airtime_count' => 0,
      'total_data_amount' => 0,
      'total_data_count' => 0,
      'total_electricity_amount' => 0,
      'total_electricity_count' => 0,
      'total_cabletv_amount' => 0,
      'total_cabletv_count' => 0,
    ];

    foreach ($transactions as $tx) {
      $package = strtolower($tx->vtupackage);
      $amount = abs($tx->amount); // Convert to positive number

      $summary = json_encode($summary);
      $summary = json_decode($summary);

      if (str_contains($package, 'airtime')) {
        $summary->total_airtime_amount += $amount;
        $summary->total_airtime_count += 1;
      } elseif (str_contains($package, 'data')) {
        $summary->total_data_amount += $amount;
        $summary->total_data_count += 1;
      } elseif (str_contains($package, 'electricity')) {
        $summary->total_electricity_amount += $amount;
        $summary->total_electricity_count += 1;
      } elseif (str_contains($package, 'tv') || str_contains($package, 'cable')) {
        $summary->total_cabletv_amount += $amount;
        $summary->total_cabletv_count += 1;
      }
    }

    return $summary;
  }

  public function getVtuDetails()
  {
    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      echo json_encode([
        'status' => 401,
        'message' => $e->getMessage()
      ]);
      exit;
    }

    $user_id = $userData->user_id;
    $email = $userData->email;

    // Get VTU transactions for this user only
    $vtu_services = $this->userModel->getVtu($email); // Assuming this returns only the user's VTU data

    $summary = [
      'total_airtime_amount' => 0,
      'total_airtime_count' => 0,
      'total_data_amount' => 0,
      'total_data_count' => 0,
      'total_electricity_amount' => 0,
      'total_electricity_count' => 0,
      'total_cabletv_amount' => 0,
      'total_cabletv_count' => 0,
    ];

    foreach ($vtu_services as $tx) {
      $package = strtolower($tx->vtupackage ?? '');
      $amount = abs($tx->amount ?? 0); // Make sure we don't work with null

      if (str_contains($package, 'airtime')) {
        $summary['total_airtime_amount'] += $amount;
        $summary['total_airtime_count'] += 1;
      } elseif (str_contains($package, 'data')) {
        $summary['total_data_amount'] += $amount;
        $summary['total_data_count'] += 1;
      } elseif (str_contains($package, 'electricity')) {
        $summary['total_electricity_amount'] += $amount;
        $summary['total_electricity_count'] += 1;
      } elseif (str_contains($package, 'tv') || str_contains($package, 'cable')) {
        $summary['total_cabletv_amount'] += $amount;
        $summary['total_cabletv_count'] += 1;
      }
    }

    echo json_encode([
      'status' => 200,
      'message' => 'VTU summary fetched successfully',
      'summary' => $summary
    ]);
    exit;
  }


public function setTransferPin()
{
    try {
    $userData = $this->requirePinUser();
    } catch (UnexpectedValueException $e) {
        ResponseHelper::error($e->getMessage(), 401);
        return;
    }

    $user_id = $userData->user_id;
    $sentData = $this->getData();
    $pin = $sentData['transfer_pin'] ?? null;

    if (!$this->hasNonEmptyPinValue($pin)) {
        ResponseHelper::error('invalid pin', 404);
        return;
    }

    $pinn = password_hash((string) $pin, PASSWORD_DEFAULT);

    if ($this->userModel->setTransferPin($pinn, $user_id)) {
        ResponseHelper::success('transfer pin set successfully');
    } else {
        ResponseHelper::error('failed to set transfer pin', 404);
    }
}

  public function setLockPin()
  {
    try {
      $userData = $this->requirePinUser();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
      return;
    }

    $sentData = $this->getData();
    $pin = $sentData['pin'] ?? null;

    if (!$this->hasNonEmptyPinValue($pin)) {
      ResponseHelper::error('invalid pin', 404);
      return;
    }

    $user_id = $userData->user_id;
    $pinn = password_hash((string) $pin, PASSWORD_DEFAULT);

    if ($this->userModel->setLockPin($pinn, $user_id)) {
      ResponseHelper::success('Lock pin set successfully');
    } else {
      ResponseHelper::error('failed to set lock pin', 404);
    }
  }
  public function verifyTransferPin()
  {
    try {
      $userData = $this->requirePinUser();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
      return;
    }

    $user_id = $userData->user_id;
    $email = $userData->email;
    $data['otp'] = (string) random_int(100000000, 999999999);

    $otp = password_hash($data['otp'], PASSWORD_DEFAULT);

    if (empty($userData->transfer_pin) || $userData->transfer_pin == "0") {
      ResponseHelper::error('Transfer pin not set', 404);
      return;
    }

    $sentData = $this->getData();
    $pin = $sentData['transfer_pin'] ?? null;

    if (!$this->hasNonEmptyPinValue($pin)) {
      ResponseHelper::error('invalid pin', 404);
      return;
    }

    if (password_verify((string) $pin, (string) $userData->transfer_pin)) {
      $this->userModel->storeTransactionKey($otp, $user_id, $email);
      ResponseHelper::success('Pin validated', ['secret_key' => $data['otp']]);
    } else {
      ResponseHelper::error('wrong pin', 404);
    }
  }
  public function forgetPassword()
  {

    $sentData = $this->getData();

    $data = [
      'email' => $sentData['email'],
    ];

    if ($this->userModel->findUserByEmail1($data['email'])) {


      $data['otp'] = $this->generateSixDigitValue();


      if ($this->passresetemailer($data, "PASSWORD RESET")) {
        $data['otp'] = password_hash($data['otp'], PASSWORD_DEFAULT);
        $this->userModel->updateResetToken($data);
        ResponseHelper::success('otp sent to ' . $data['email']);
      } else {
        ResponseHelper::error('failed to send email', 404);
      }
    } else {
      ResponseHelper::error('failed to find email', 404);
    }
  }

  public function setNotifications()
  { ///method start

    //  try {
    //     $userData = $this->RouteProtecion();
    // } catch (UnexpectedValueException $e) {
    //     $res = [
    //       'status' => 401,
    //       'message' =>  $e->getMessage(),
    //     ];
    //     print_r(json_encode($res));
    //     exit;
    // }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') { ///post start

      //Sanitize POST data
      $loginData = $this->getData();
      // Process form


      // Init data
      $data = [

        'headers' => $loginData['headers'],
        'text' => $loginData['text'],
        'img' => $loginData['img'],
      ];

      //echo $data['email'];
      //exit;

      // Validate Name
    if (empty($data['headers'])) {
        ResponseHelper::error('enter headers', 404);
    }
    if (empty($data['text'])) {
        ResponseHelper::error('enter text', 404);
    }
    if (empty($data['img'])) {
        ResponseHelper::error('enter img', 404);
    }


      if ($this->userModel->setNotifications($data)) {
        ResponseHelper::success('notification set successfully');
      } else {
        ResponseHelper::error('an error occured', 404);
      };



      //$resetPassword->
    } else {
      ResponseHelper::error('Wrong method', 401);
    } ///post end

  }


  public function verifyPhone()
  {
    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
      return;
    }


    $sentData = $this->getData();

    if ($this->userModel->findUserByPhonexxx($sentData['phone'])) {
        ResponseHelper::error('phone number already in use .', 404);
    }
    $url = "https://v3.api.termii.com/api/sms/send";
    $senderID = "PAYKING";
    $senderIDazz = "" . $this->generateSixDigitValue() . "";
    $termiiApiKey = env('TERMII_API_KEY', '');

    if (!is_string($termiiApiKey) || trim($termiiApiKey) === '') {
      ResponseHelper::error('Phone verification is not configured', 503);
      return;
    }

    $messageBody = '' . $senderIDazz . '.  Do not share it with anyone.
 
';
    // Prepare the payload
    $payload = json_encode([
      "sms" => $messageBody,
      "to" => '234' . substr($sentData['phone'], 1),
      "senderID" => $senderID,
      "channel" => "generic",
      "type" => "plain",
      "from" => "PayKing",
      "api_key" => $termiiApiKey
    ]);
    $payload2 = array(
      "messageText" => $messageBody,
      "mobileNumber" => $sentData['phone'],
      "senderID" => $senderID
    );



    $response = $this->sendCustomMessage($url, $payload);
    $ress = json_decode($response);

    if ($ress->message == "Successfully Sent" && $ress->code == "ok") {
      $payload2['otp'] = password_hash($senderIDazz, PASSWORD_DEFAULT);
      $this->userModel->updateResetToken234($payload2, $userData->email);
      ResponseHelper::success('Code sent to Phone Successfully.');
    } else {
      ResponseHelper::error('failed to verifyPhone', 404);
    }
  }

  public function verifyPhoneCode()
  {
    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      $res = [
        'status' => 401,
        'message' =>  $e->getMessage(),
      ];
      print_r(json_encode($res));
      exit;
    }


    $sentData = $this->getData();

    $user = $this->userModel->findUserByEmail360x($userData->email);
    $otp = $sentData['phone_code'];
    $hash_otp = $user->email_reset_token;
    if (password_verify($otp, $hash_otp)) {
      $this->userModel->updateResetToken23456($userData->email);
      ResponseHelper::success("Phone Number Validated Successfully.");
    } else {
      // Debug info removed for optimization
      ResponseHelper::error('failed to validate number', 404);
    }
  }


  public function forgetTransferPin()
  {

    try {
      $userData = $this->requirePinUser();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
      return;
    }

    $sentData = $this->getData();
    $user_id = $userData->user_id;
    $data['email'] = $userData->email;

    if ($userData->transfer_pin == 0 || $userData->transfer_pin == "0") {
      ResponseHelper::error('Transfer pin not set', 404);
    }

    if ($this->userModel->findUserByEmail1($data['email'])) {


      $data['otp'] = $this->generateSixDigitValue();


      if ($this->passresetemailer($data, "TRANSFER PIN RESET")) {
        $data['otp'] = password_hash($data['otp'], PASSWORD_DEFAULT);
        $this->userModel->updateResetToken($data);
        ResponseHelper::success('otp sent to ' . $data['email']);
      } else {
        ResponseHelper::error('failed to send email', 404);
      }

      //   $otp = $data['otp'];

      //   if($this->emailSent($data)){
      //     $data['otp'] = password_hash($data['otp'], PASSWORD_DEFAULT);
      //     $this->userModel->updateResetToken($data);
      //     $res = json_encode(array(
      //       'status'=> true,
      //       'message'=> 'otp sent',
      //       'otp' => $otp

      //       ));
      //       http_response_code(200);
      //       print_r($res);
      //   }else {
      //     $res = json_encode(array(
      //       'status'=> false,
      //       'message'=> 'otp not sent'
      //       ));
      //       http_response_code(404);
      //       print_r($res);
      //   }






    } else {
        ResponseHelper::error('failed to find email', 404);
    }
  }


  public function changeTransferPin()
  {
    try {
      $userData = $this->requirePinUser();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
      return;
    }

    $user_id = $userData->user_id;

    $sentData = $this->getData();

    $data = [
      'old_pin' => $sentData['old_pin'] ?? null,
      'new_pin' => $sentData['new_pin'] ?? null,
      'confirm_new_pin' => $sentData['confirm_new_pin'] ?? null,
    ];
    foreach ($data as $value) {
      if (!$this->hasNonEmptyPinValue($value)) {
        ResponseHelper::error('incomplete pin data', 400);
        return;
      }
    }
    if (empty($userData->transfer_pin) || $userData->transfer_pin == "0") {
      ResponseHelper::error('Transfer pin not set', 404);
      return;
    }
    if ((string) $data['new_pin'] !== (string) $data['confirm_new_pin']) {
      ResponseHelper::error('pin does not match', 400);
      return;
    }
    if (password_verify((string) $data['old_pin'], (string) $userData->transfer_pin)) {
      $newPinHash = password_hash((string) $data['new_pin'], PASSWORD_DEFAULT);
      if ($this->userModel->setTransferPin($newPinHash, $user_id)) {
        ResponseHelper::success('transfer pin set successfully');
      } else {
        ResponseHelper::error('failed to set transfer pin', 404);
      }
    } else {
      ResponseHelper::error('wrong pin', 404);
    }
  }

  public function changeLockPin()
  {
    try {
      $userData = $this->requirePinUser();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
      return;
    }

    $user_id = $userData->user_id;
    $sentData = $this->getData();

    $data = [
      'old_pin' => $sentData['old_pin'] ?? null,
      'new_pin' => $sentData['new_pin'] ?? null,
      'confirm_new_pin' => $sentData['confirm_new_pin'] ?? null,
    ];

    if (!$this->hasNonEmptyPinValue($data['old_pin']) || !$this->hasNonEmptyPinValue($data['new_pin']) || !$this->hasNonEmptyPinValue($data['confirm_new_pin'])) {
      ResponseHelper::error('incomplete pin data', 404);
      return;
    }

    $storedPin = $userData->lock_pin ?? null;
    if (empty($storedPin) || $storedPin == "0") {
      ResponseHelper::error('Lock pin not set', 404);
      return;
    }
    if ((string) $data['new_pin'] !== (string) $data['confirm_new_pin']) {
      ResponseHelper::error('pin does not match', 404);
      return;
    }
    if (password_verify((string) $data['old_pin'], (string) $storedPin)) {
      $newPinHash = password_hash((string) $data['new_pin'], PASSWORD_DEFAULT);
      if ($this->userModel->setLockPin($newPinHash, $user_id)) {
        ResponseHelper::success('lock pin set successfully');
      } else {
        ResponseHelper::error('failed to set lock pin', 404);
      }
    } else {
      ResponseHelper::error('wrong pin', 404);
    }
  }

  public function resetTransferPin()
  {
    try {
      $userData = $this->requirePinUser();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
      return;
    }

    $user_id = $userData->user_id;
    $sentData = $this->getData();

    $data = [
      'otp' => $sentData['otp'] ?? null,
      'transfer_pin' => $sentData['transfer_pin'] ?? null,
      'confirm_transfer_pin' => $sentData['confirm_transfer_pin'] ?? null,
    ];

    foreach ($data as $key => $value) {
      if (!$this->hasNonEmptyPinValue($value)) {
        ResponseHelper::error("Incomplete params: {$key} is required.", 404);
        return;
      }
    }

    if ((string) $data['confirm_transfer_pin'] !== (string) $data['transfer_pin']) {
      ResponseHelper::error('password do not match', 404);
      return;
    }

    $hash_otp = $userData->password_reset_token ?? null;
    if ($hash_otp !== null && password_verify((string) $data['otp'], (string) $hash_otp)) {
      $pinn = password_hash((string) $data['transfer_pin'], PASSWORD_DEFAULT);
      if ($this->userModel->setTransferPin($pinn, $user_id)) {
        ResponseHelper::success('transfer pin set successfully');
      } else {
        ResponseHelper::error('failed to set transfer pin', 404);
      }
    } else {
      ResponseHelper::error('invalid or expired otp', 404);
    }
  }

  public function resetPassword()
  {

    $sentData = $this->getData();


    $data = [
      'otp' => $sentData['otp'],
      'password' => $sentData['password'],
      'confirm_password' => $sentData['confirm_password'],
    ];

    foreach ($sentData as $key => $value) {

      if (!isset($key) && is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }

    if ($data['confirm_password'] != $data['password']) {
        ResponseHelper::error('password do not match', 404);
    }

    $usersData = $this->userModel->findAllUsers2();

    $otpMatched = false; // Flag to track OTP match

    foreach ($usersData as $user) {
      $hash_otp = $user->password_reset_token;
      if (password_verify($data['otp'], $hash_otp)) {
        $otpMatched = true; // OTP match found
        ///// Send reset password email /////
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['user_id'] = $user->user_id;
        $data['email'] = $user->email;

        if ($this->userModel->updatePassword($data)) {
          ResponseHelper::success('Password reset successful');
        } else {
          ResponseHelper::error('Password reset failed', 404);
        }
        break; // Exit the loop after successful OTP verification and processing
      }
    }

    // If no match was found, send a response indicating the OTP is incorrect
    // If no match was found, send a response indicating the OTP is incorrect
    if (!$otpMatched) {
        ResponseHelper::error('Invalid OTP', 400);
    }
  }


  public function verifyLockPin()
  {

    $sentData = $this->getData();


    $data = [
      'otp' => $sentData['pin'],
    ];

    foreach ($sentData as $key => $value) {

      if (!isset($key) && is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }

    $usersData = $this->userModel->findAllUsers2();

    $otpMatched = false; // Flag to track OTP match

    foreach ($usersData as $user) {
      $hash_otp = $user->password_reset_token;
      if (password_verify($data['otp'], $hash_otp)) {
        $otpMatched = true; // OTP match found
        ///// Send reset password email /////
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['user_id'] = $user->user_id;
        $data['email'] = $user->email;

        if ($this->userModel->updatePassword($data)) {
          ResponseHelper::success('Password reset successful');
        } else {
          ResponseHelper::error('Password reset failed', 404);
        }
        break; // Exit the loop after successful OTP verification and processing
      }
    }

    // If no match was found, send a response indicating the OTP is incorrect
    // If no match was found, send a response indicating the OTP is incorrect
    if (!$otpMatched) {
      ResponseHelper::error('Invalid OTP', 400);
    }
  }






  public function activateAccount()
  {

    $sentData = $this->getData();


    $data = [
      'otp' => $sentData['otp'],
      'email' => $sentData['email'],

    ];


    foreach ($sentData as $key => $value) {

      if (!isset($key) && is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }


    $usersData = $this->userModel->findUserByEmail_dett($data['email']);

    //   if ($usersData->activationx = 1) {
    //         $response = array(
    //           'status' => 'false',
    //           'message' => 'account activated',
    //         );
    //         http_response_code(404);
    //         print_r(json_encode($response));
    //         exit;
    //   }
    //  print_r($usersData);
    //       exit;

    $hash_otp = $usersData->email_reset_token;
    if (password_verify($data['otp'], $hash_otp)) {
      ///// send reset password email////

      $data['user_id'] = $usersData->user_id;
      $data['email']   =   $usersData->email;
      $data['password'] = $usersData->password;
      $data['deviceToken'] = $usersData->deviceToken;
      if ($this->userModel->register_user($data) &&  $this->createAccount($data) && $this->userModel->activateAccount($data)) {

        $log = $this->loginRegisteredUser($data);
        $mail =  $this->welcome_emailer($data, "WELCOME TO PAYKING");

        ResponseHelper::success('Registration successful', ['access_token' => $log]);
      } else {
        ResponseHelper::error('Registration failed', 404);
      }
    } else {
      ResponseHelper::error('failed to verify otp', 404);
    }
  }


  public function activateAccount_admin()
  {

    $sentData = $this->getData();


    $data = [
      'otp' => $sentData['otp'],
      'email' => $sentData['email']
    ];

    foreach ($sentData as $key => $value) {

      if (!isset($key) && is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }


    $usersData = $this->userModel->findUserByEmail_dett($data['email']);
    //  print_r($usersData);
    // //       exit;
    //   if ($usersData->activationx = 1) {
    //         $response = array(
    //           'status' => 'false',
    //           'message' => 'account activated',
    //         );
    //         http_response_code(404);
    //         print_r(json_encode($response));
    //         exit;
    //   }
    // 

    $hash_otp = $usersData->email_reset_token;
    if (password_verify($data['otp'], $hash_otp)) {
      ///// send reset password email////

      $data['user_id'] = $usersData->user_id;
      $data['email'] = $usersData->email;
      $data['password'] = $usersData->password;
      $data['reg_id'] = $usersData->registerer_id;
      if ($this->userModel->register_admin($data) &&  $this->createAccount($data) && $this->userModel->activateAccount($data)) {
        $log = $this->loginRegisteredUser($data);
        $mail =  $this->welcome_emailer($data, "WELCOME TO PAYKING");

        ResponseHelper::success('Registration successful', ['access_token' => $log]);
      } else {
        ResponseHelper::error('Registration failed', 404);
      }
    } else {
      ResponseHelper::error('failed to verify otp', 404);
    }
  }

  public function  resendotp()
  {
    $sentData =  $this->getData();

    $data['otp'] = $this->generateSixDigitValue();
    $data['email'] = $sentData['email'];

    if (empty($data['email'])) {
      ResponseHelper::error('enter email', 404);
    }


    if ($this->sendOTPEmail($data)) {
      $data['otp'] = password_hash($data['otp'], PASSWORD_DEFAULT);
      $this->userModel->updateResetToken2($data);
      ResponseHelper::success('otp sent to ' . $data['email']);
    } else {
      ResponseHelper::error('failed to send email', 404);
    }
  }



  public function verifyLogin()
  {


    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }


    ResponseHelper::success('login valid');
  }

  public function loginRegisteredUser($data)
  {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      // print_r(json_encode($data));

      $loginDatax = $this->userModel->loginUser($data['email'], $data['deviceToken']);

      $postPassword = $data['password'];
      $loginStatus = $loginDatax->activationx;
      $hash_password = $loginDatax->password;
      $email = $loginDatax->email;
      $user_id = $loginDatax->user_id;



      if ($loginStatus < 1) {
        // $data['msg'] = 'User access has Not Been activad, please contact or visit NIS secretariat !';


        ResponseHelper::error('login status disabled', 403);
      }
      if (!$this->userModel->verifyDtoken($data['deviceToken'])) {


        ResponseHelper::error('login in with a new device', 400);
      }



      $tokenX = $token = "token" . md5(date("dmyhis") . rand(1222, 89787)) . md5(date("dmyhis") . rand(1222, 89787)) . md5(date("dmyhis") . rand(1222, 89787)) . md5(date("dmyhis") . rand(1222, 89787)) . md5(date("dmyhis") . rand(1222, 89787));



      //echo $tokenX;
      $this->userModel->updateToken($user_id, $tokenX, $email);

      $loginData = $this->userModel->findLoginByToken($tokenX);

      $userData = $this->userModel->findUserByEmail_det2($loginData->email);

      $accountDetails = $this->userModel->getAccountDetails($userData->account_id, $user_id);
      $initData = [
        'loginData' => $loginData,
        'userAccount' => $accountDetails,
      ];



      // ///////end


      // $initData = [
      //   'loginData' => $loginData,
      //   'userData' => $userData,
      // ];

      // ////////////start

      // /* 
      // $userID = $initData['loginData']->user_id;
      // $appToken = $initData['loginData']->token; */
      $datatoken = [
        'user_id' => $user_id,
        'email' => $email,
        'appToken' => $initData['loginData']->token,
      ];
      $JWT_token = $this->getMyJsonID($datatoken, $this->serverKey);



      $response = array(
        'status' => true,
        'access_token' => $JWT_token,
        //   'datatoken' => $datatoken,
        'message' => 'success',
        'data' => $initData,

      );


      return $JWT_token;


      /////////////////end





      ///////////////////////////////end

    } else {


      ResponseHelper::error('invalid user login detail', 404, ['data' => $data]);


      //$data['msg'] = 'Invalid Login Access !';

      // redirect('users/login/1');
    }
  }

  public function getuserDetails()
  {


    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }

    $user_id = $userData->user_id;

    // $user = $this->userModel->getUser($user_id);
    $user = $this->userModel->getUser($user_id);

    $userData = $this->userModel->findUserByEmail_det2($userData->email);
    $accountDetails = $this->userModel->getAccountDetails($userData->account_id, $user_id);

    $user = (array)$user;
    $user = array_filter($user, function ($value, $key) {
      return $key !== "password" && $key !== "work" && $key !== "suffix" && $key !== "rank" && $key !== "memberType" && $key !== "maritalStatus" && $key !== "nextKin" && $key !== "activeCode" && $key !== "altEmail" && $key !== "nin_no" && $key !== "nin_img" && $key !== "bvn_no";
    }, ARRAY_FILTER_USE_BOTH);


    if (!empty($user->nin_no)) {
      $user['ninVerified'] = 1;
    } else {
      $user['ninVerified'] = 0;
    }

    if (!empty($user->nin_img)) {
      $user['nin_imgVerified'] = 1;
    } else {
      $user['nin_imgVerified'] = 0;
    }


    if (!empty($user->bvn_no)) {
      $user['bvnVerified'] = 1;
    } else {
      $user['bvnVerified'] = 0;
    }


    if ($user && $userData) {
      ResponseHelper::success('success', ['user' => $user, "account" => $accountDetails]);
    } else {
      ResponseHelper::error('faild', 404);
    }
  }

  public function getNotifications()
  {


    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }
    $user = $this->userModel->getNotifications($userData->user_id);


    // if($user){
    ResponseHelper::success('success', $user);
    // }else {
    //   $res = json_encode(array(
    //     'status'=> false,
    //     'message'=> 'faild'
    //     ));
    //     http_response_code(404);
    //     print_r($res);
    // }
  }
  public function getallUsers()
  {


    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }


    $user =    $usersData = $this->userModel->findAllUsers();


    if ($user) {
      ResponseHelper::success('success', $user);
    } else {
      ResponseHelper::error('faild', 404);
    }
  }

  public function edit_user()
  {
    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    } catch (DomainException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }


    $sentData = $this->getData();
    $data = array(
      "fullname" => trim($sentData["fullname"]),
      "uname" => trim($sentData["username"]),
      "dob" => trim($sentData["dob"]),
      "gender" => trim($sentData["gender"]),
      "address" => trim($sentData["address"]),
      "email" => $userData->email,
      "image" => $_FILES["image"],
      "nin" => trim($sentData["nin"]),
      "user_id" =>  $userData->user_id,

    );

    foreach ($data as $key => $value) {
      if (is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }

    if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
      ResponseHelper::error('Invalid email.', 404);
    }

    if (!$this->userModel->findUserByEmail1($data['email'])) {
      ResponseHelper::error("User Not registered.", 404);
    }
    // if(!$this->userModel->findUserByNin($data['nin'])){
    //     http_response_code(404);
    //   print_r(json_encode(array(
    //     "status" => false,
    //     "message" => "NIN already Used registered."
    // )));
    // exit;
    // }

    //   $new_image_names = [];

    //   $extensions = ["jpeg", "png", "jpg"];
    //   $types = ["image/jpeg", "image/jpg", "image/png"];

    //   $image_fields = ['image'];

    //   foreach ($image_fields as $image_field) {
    //       if (isset($data[$image_field])) {
    //           $img_name = $data[$image_field]['name'];
    //           $img_type = $data[$image_field]['type'];
    //           $tmp_name = $data[$image_field]['tmp_name'];
    //           $img_explode = explode('.', $img_name);
    //           $img_ext = end($img_explode);

    //           if (in_array($img_ext, $extensions) === true) {
    //               if (in_array($img_type, $types) === true) {
    //                   $time = time();
    //                   $new_img_name = $time . "_" . $img_name;
    //                   if (move_uploaded_file($tmp_name,  "assets/img/attachment/" . $new_img_name)) {
    //                       $new_image_names[$image_field] = (string)(URLROOT . "/assets/img/attachment/" . $new_img_name); 
    //                   } else {
    //                       $response = array(
    //                           'status' => 'false',
    //                           'message' => "Upload failed for $image_field",
    //                       );
    //                       http_response_code(404);
    //                       print_r(json_encode($response));
    //                       exit;
    //                   }
    //               } else {
    //                   $response = array(
    //                       'status' => 'false',
    //                       'message' => "Invalid file type for $image_field. Allowed types are: " . implode(', ', $types),
    //                   );
    //                   http_response_code(404);
    //                   print_r(json_encode($response));
    //                   exit;
    //               }
    //           } else {
    //               $response = array(
    //                   'status' => 'false',
    //                   'message' => "Invalid file extension for $image_field. Allowed extensions are: " . implode(', ', $extensions),
    //               );
    //               http_response_code(404);
    //               print_r(json_encode($response));
    //               exit;
    //           }
    //       } else {
    //           $response = array(
    //               'status' => 'false',
    //               'message' => "$image_field not set",
    //           );
    //           http_response_code(404);
    //           print_r(json_encode($response));
    //           exit;
    //       }
    //   }

    //   foreach ($new_image_names as $key => $value) {
    //       $data[$key] = $value;
    //   }

    $image_fields = [
      "image" => $_FILES["image"]
    ];
    $new_image_names = $this->saveMultipleToS3Bucket($image_fields);

    foreach ($new_image_names as $key => $value) {
      $data[$key] = $value;
    }
    
    //echo json_encode($data);exit;

    if ($this->userModel->edit_user($data)) {
      ResponseHelper::success('edit profile successful');
    } else {
      ResponseHelper::error('registeration failed', 404);
    }
  }



  public function register_user()
  {

    $sentData = $this->getData();
    $data = array(
      "email" => trim($sentData["email"]),
      "user_id" =>  $this->generateUniqueId(),
      "password" => trim($sentData["password"]),
      "confirm_password" => trim($sentData["confirm_password"]),
      'deviceToken' => $sentData['d_token']
    );

    if (!isset($data['deviceToken'])) {
      ResponseHelper::error('device token is missing', 404);
    }

    foreach ($data as $key => $value) {
      if (is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }

    if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
      ResponseHelper::error('Invalid email.', 404);
    }

    // if($this->userModel->findUserByEmail1($data['email'])){
    //     http_response_code(404);
    //   print_r(json_encode(array(
    //     "status" => false,
    //     "message" => "User Already registered ."
    // )));
    // exit;
    // }




    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

 $data['otp'] = $this->generateSixDigitValue();
 
//  echo json_encode($data);exit;

    if ($this->userModel->register_user2($data)) {
      //   $this->userModel->setUpdevice($data);
     


      if ($this->sendOTPEmail($data)) {
        $data['otp'] = password_hash($data['otp'], PASSWORD_DEFAULT);
        $this->userModel->updateResetToken2($data);
        $res = (array(
          'status_code' => 200,
          'status' => true,
          'message' => 'otp sent to ' . $data['email']
        ));
        $this->handleResponse($res);
      } else {
        $res = (array(
          'status_code' => 404,
          'status' => false,
          'message' => 'failed to send email'
        ));
        $this->handleResponse($res);
      }
    } else {
      ResponseHelper::error('registeration failed', 404);
    }
  }


  public function register_admin()
  {
    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    } catch (DomainException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }
    //   echo json_encode($userData);exit;

    if ($userData->roleID == 6  || $userData->roleID == 7) {
    } else {
      ResponseHelper::error("you are not permitted", 401);
    }
    $sentData = $this->getData();
    $data = array(
      "email" => trim($sentData["email"]),
      "user_id" =>  $this->generateUniqueId(),
      "password" => trim($sentData["password"]),
      "confirm_password" => trim($sentData["confirm_password"]),
      'reg_id' => $userData->user_id
    );

    foreach ($data as $key => $value) {
      if (is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }

    if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
      ResponseHelper::error('Invalid email.', 404);
    }

    if ($this->userModel->findUserByEmail1($data['email'])) {
      ResponseHelper::error("User Already registered .", 404);
    }


    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);



    if ($this->userModel->register_admin2($data)) {

      $data['otp'] = $this->generateSixDigitValue();


      if ($this->sendOTPEmail($data)) {
        $data['otp'] = password_hash($data['otp'], PASSWORD_DEFAULT);
        $this->userModel->updateResetToken32($data);
        $res = (array(
          'status_code' => 200,
          'status' => true,
          'message' => 'otp sent to ' . $data['email']
        ));
        $this->handleResponse($res);
      } else {
        $res = (array(
          'status_code' => 404,
          'status' => false,
          'message' => 'failed to send email'
        ));
        $this->handleResponse($res);
      }
    } else {
      ResponseHelper::error('registeration failed', 404);
    }
  }
  public function google_sign_up()
  {

    $sentData = $this->getData();
    $data = array(
      "email" => trim($sentData["email"]),
      "user_id" =>  $this->generateUniqueId(),
      "password" => trim($sentData["password"]),
      "confirm_password" => trim($sentData["confirm_password"]),
      'deviceToken' => $sentData['d_token']
    );

    foreach ($data as $key => $value) {
      if (is_string($value) && $value === "") {
        ResponseHelper::error("Incomplete params: " . $key . " is required.", 404);
      }
    }

    if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
      ResponseHelper::error('Invalid email.', 404);
    }

    if ($this->userModel->findUserByEmail1($data['email'])) {
      ResponseHelper::error("User Already registered .", 404);
    }


    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);



    if ($this->userModel->register_user($data) &&  $this->createAccount($data) && $this->userModel->activateAccount($data)) {
      //   $this->userModel->setUpdevice($data);
      $log = $this->loginRegisteredUser($data);
      $mail =  $this->welcome_emailer($data, "WELCOME TO PAYKING");

      ResponseHelper::success('Registration successful', ['access_token' => $log]);
    } else {
      ResponseHelper::error('Registration failed', 404);
    }
  }

  public function createAccount($data)
  {
    $data['account_id'] = 'ACC_' . $this->generateUniqueId();
    return $this->userModel->createAccount($data);
  }



  public function disable_user()
  {
    try {
      $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
      ResponseHelper::error($e->getMessage(), 401);
    }
    // Only allow admin/supervisor (roleID 6 or 7)
    if (!in_array($userData->roleID, [6, 7,8])) {
      ResponseHelper::error("you are not permitted", 401);
    }
    $sentData = $this->getData();
    $email = trim($sentData["email"] ?? '');
    $user_id = trim($sentData["user_id"] ?? '');
    if (empty($email) || empty($user_id)) {
      ResponseHelper::error('email and user_id are required', 400);
    }
    $result = $this->userModel->disableAccount($email, $user_id);
    if ($result) {
      ResponseHelper::success('User account disabled successfully');
    } else {
      ResponseHelper::error('Failed to disable user account', 500);
    }
  }
  
  public function getVtuBalance()
{
    $response = $this->makeGetRequestVT2("https://vtpass.com/api/balance");
    $balance = json_decode($response);
   return $balance->contents->balance;
}
  
  public function getUserStatistics()
{
    try {
        $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
        $res = [
            'status' => 401,
            'message' => $e->getMessage(),
        ];
        print_r(json_encode($res));
        exit;
    }

    // Only allow admin/supervisor (roleID 6, 7, 8)
    if (!in_array($userData->roleID, [6, 7, 8])) {
        $res = [
            'status' => 401,
            'message' => "You are not permitted",
        ];
        http_response_code(401);
        print_r(json_encode($res));
        exit;
    }

    // ===== TOTAL USERS =====
    $users = $this->userModel->findAllUsers();
    $total_users = is_array($users) ? count($users) : 0;

    // ===== ALL TRANSACTIONS =====
    $transactions = $this->transactionModel->getAllTransactions();
    $total_inflow = 0;
    $total_outflow = 0;
    $total_vtu = 0;
    $total_electricity = 0;
    $total_cable = 0;

    $airtime_count = 0;
    $airtime_total = 0;
    $data_count = 0;
    $data_total = 0;
    $electricity_count = 0;
    $electricity_total = 0;
    $cable_count = 0;
    $cable_total = 0;
    $crypto_count = 0;
    $crypto_total = 0;
    $giftcard_count = 0;
    $giftcard_total = 0;
    $deposit_count = 0;
    $deposit_total = 0;
    $withdrawal_count = 0;
    $withdrawal_total = 0;

    // ✅ Normalize data
    if (is_object($transactions) && isset($transactions->data) && is_array($transactions->data)) {
        $transactionList = $transactions->data;
    } elseif (is_array($transactions)) {
        $transactionList = $transactions;
    } else {
        $transactionList = [];
    }

    foreach ($transactionList as $tx) {
        if (is_object($tx)) {
            $tx = (array)$tx;
        }

        $amount = isset($tx['amount']) ? abs($tx['amount']) : 0;
        $type = strtolower($tx['transaction_type'] ?? '');
        $service = strtolower($tx['service_type'] ?? '');
        $status = strtolower($tx['status'] ?? '');

        // Inflow/outflow
        if ($type === 'inflow') $total_inflow += $amount;
        if ($type === 'outflow') $total_outflow += $amount;

        // Services
        switch ($service) {
            case 'vtu':
                $total_vtu += $amount;
                break;
            case 'airtime':
                $airtime_count++;
                $airtime_total += $amount;
                break;
            case 'data':
                $data_count++;
                $data_total += $amount;
                break;
            case 'electricity':
                $total_electricity += $amount;
                $electricity_count++;
                $electricity_total += $amount;
                break;
            case 'cable':
                $total_cable += $amount;
                $cable_count++;
                $cable_total += $amount;
                break;
            case 'crypto':
                if ($status === 'success') {
                    $crypto_count++;
                    $crypto_total += $amount;
                }
                break;
            case 'giftcard':
                if ($status === 'success') {
                    $giftcard_count++;
                    $giftcard_total += $amount;
                }
                break;
            case 'deposit':
                $deposit_count++;
                $deposit_total += $amount;
                break;
            case 'withdrawal':
                $withdrawal_count++;
                $withdrawal_total += $amount;
                break;
        }
    }

    // ===== VTU BALANCE (Only for admin/supervisor) =====
    $vtupass_balance = null;
    if (!in_array($userData->roleID, [9])) { // Assuming 9 = customer rep
       
            // $vtupassModel = new Vtupass();
            $vtupass_balance = $this->getVtuBalance();
        
    }

    // ===== THRIFT / OSUSU =====
    $active_osusu = 0;
    $active_thrift = 0;
    $total_thrift_amount = 0;
    $total_osusu_amount = 0;

   
        $osusu = $this->thriftModel->getAllOsusuxx();
        $thrift = $this->thriftModel->getAllThriftxx();
        $active_osusu = is_array($osusu) ? count($osusu) : 0;
        $active_thrift = is_array($thrift) ? count($thrift) : 0;
        $total_thrift_amount = $this->thriftModel->getTotalThriftAmount();
        $total_osusu_amount = $this->thriftModel->getTotalOsusuAmount();


    // ===== CROWD FUNDING CAMPAIGNS =====
    $open_campaigns = 0;
    $campaign_total_gathered = 0;

        $campaignStats = $this->thriftModel->getOpenCampaignStats();
        $open_campaigns = $campaignStats['open_campaigns'] ?? 0;
        $campaign_total_gathered = $campaignStats['total_gathered'] ?? 0;
        $campaign_target_total = $campaignStats['target_total'] ?? 0;


    // ===== FINAL RESPONSE =====
    $res = [
        'status' => true,
        'message' => 'User statistics fetched successfully',
        'data' => [
            'total_users' => $total_users,
            'total_transaction' => count($transactionList),
            'total_inflow' => $total_inflow,
            'total_outflow' => $total_outflow,

            'airtime' => [
                'count' => $airtime_count,
                'amount' => $airtime_total,
            ],
            'data' => [
                'count' => $data_count,
                'amount' => $data_total,
            ],
            'electricity' => [
                'count' => $electricity_count,
                'amount' => $electricity_total,
            ],
            'cable_tv' => [
                'count' => $cable_count,
                'amount' => $cable_total,
            ],

            'total_vtu' => $total_vtu,
            'total_electricity' => $total_electricity,
            'total_cable' => $total_cable,

            'vtupass_balance' => $vtupass_balance,

            'active_thrift' => $active_thrift,
            'active_osusu' => $active_osusu,
            'total_thrift_amount' => $total_thrift_amount,
            'total_osusu_amount' => $total_osusu_amount,

            'deposits' => [
                'count' => $deposit_count,
                'amount' => $deposit_total,
            ],
            'withdrawals' => [
                'count' => $withdrawal_count,
                'amount' => $withdrawal_total,
            ],

            'crypto' => [
                'count' => $crypto_count,
                'amount' => $crypto_total,
            ],
            'giftcard' => [
                'count' => $giftcard_count,
                'amount' => $giftcard_total,
            ],

            'crowdfunding' => [
                'open_campaigns' => $open_campaigns,
                'total_gathered' => $campaign_total_gathered,
                'target_total' => $campaign_target_total,
            ],
        ]
    ];

    print_r(json_encode($res));
    exit;
}


 public function getUserStatistics2()
{
    try {
        $userData = $this->RouteProtecion();
    } catch (UnexpectedValueException $e) {
        $res = [
            'status' => 401,
            'message' => $e->getMessage(),
        ];
        print_r(json_encode($res));
        exit;
    }

    // Only allow admin/supervisor (roleID 6, 7, 8)
    if (!in_array($userData->roleID, [6, 7, 8])) {
        $res = [
            'status' => 401,
            'message' => "You are not permitted",
        ];
        http_response_code(401);
        print_r(json_encode($res));
        exit;
    }

    // Total users
    $users = $this->userModel->findAllUsers();
    $total_users = is_array($users) ? count($users) : 0;

    // Total inflow/outflow
    $transactions = $this->transactionModel->getAllTransactions();
    $total_inflow = 0;
    $total_outflow = 0;
    $total_vtu = 0;
    $total_electricity = 0;
    $total_cable = 0;

    // ✅ Handle both object and array forms
    if (is_object($transactions) && isset($transactions->data) && is_array($transactions->data)) {
        $transactionList = $transactions->data;
    } elseif (is_array($transactions)) {
        $transactionList = $transactions;
    } else {
        $transactionList = [];
    }

    foreach ($transactionList as $tx) {
        // If transaction is object, convert to array for safety
        if (is_object($tx)) {
            $tx = (array)$tx;
        }

        $amount = isset($tx['amount']) ? abs($tx['amount']) : 0;

        // Inflow/outflow
        if (!empty($tx['transaction_type'])) {
            $type = strtolower($tx['transaction_type']);
            if ($type === 'inflow') {
                $total_inflow += $amount;
            } elseif ($type === 'outflow') {
                $total_outflow += $amount;
            }
        }

        // Service type
        if (!empty($tx['service_type'])) {
            $service = strtolower($tx['service_type']);
            if ($service === 'vtu') {
                $total_vtu += $amount;
            } elseif ($service === 'electricity') {
                $total_electricity += $amount;
            } elseif ($service === 'cable') {
                $total_cable += $amount;
            }
        }
    }

    // Active osusu/thrift
    $active_osusu = 0;
    $active_thrift = 0;
    if (class_exists('Thrift')) {
        $thriftModel = new Thrift();
        $osusu = $thriftModel->getAllOsusu($userData->user_id);
        $thrift = $thriftModel->getAllThrift($userData->user_id);
        $active_osusu = is_array($osusu) ? count($osusu) : 0;
        $active_thrift = is_array($thrift) ? count($thrift) : 0;
    }

    $res = [
        'status' => true,
        'message' => 'User statistics fetched successfully',
        'data' => [
            'total_users' => $total_users,
            'total_inflow' => $total_inflow,
            'total_outflow' => $total_outflow,
            'total_vtu' => $total_vtu,
            'total_electricity' => $total_electricity,
            'total_cable' => $total_cable,
            'active_osusu' => $active_osusu,
            'active_thrift' => $active_thrift,
            'total_transaction' => count($transactions) ?? 0
        ]
    ];

    print_r(json_encode($res));
    exit;
}

    public function electricity_sudo()
    {
        try {
            $userData = $this->RouteProtecion();
        } catch (UnexpectedValueException $e) {
            $res = [
                'status' => 401,
                'message' => $e->getMessage(),
            ];
            http_response_code(401);
            print_r(json_encode($res));
            exit;
        } catch (DomainException $e) {
            http_response_code(401);
            $res = [
                'status' => 401,
                'message' => $e->getMessage(),
            ];
            print_r(json_encode($res));
            exit;
        }

        $balance = $this->getUserBalance2();
        $jsonData = $this->getData();

        // Pin Verification
        if (!isset($jsonData['pin'])) {
             echo json_encode(['status' => false, 'message' => 'Pin is required']);
             exit;
        }
        $key = $jsonData['pin'];
        $v_key = $this->verifyTransferPin2($key);
        $decoded = json_decode($v_key);
        if ($decoded->status !== true) {
            http_response_code(401);
            print_r(json_encode($decoded));
            exit;
        }

        // Input Validation
        $amount = $jsonData['amount'] ?? '';
        $phone = $jsonData['phone'] ?? '';
        $serviceID = $jsonData['serviceID'] ?? ''; // e.g., ikeja_electric
        $meterNo = $jsonData['meterNo'] ?? '';
        $billerCode = $jsonData['billerCode'] ?? ''; // e.g., IKEDC

        if (empty($amount) || empty($phone) || empty($serviceID) || empty($meterNo)) {
            echo json_encode([
                "status" => false,
                "message" => "Incomplete parameters. Required: amount, phone, serviceID, meterNo"
            ]);
            exit;
        }

        if ($balance->savings < $amount) {
            echo json_encode([
                "status_code" => 401,
                "status" => false,
                "message" => "Insufficient Balance"
            ]);
            exit;
        }

        // Get SafeHaven Credentials
        $token = $this->getSafeHavenCredentials();
        if (!$token || !isset($token->access_token)) {
             echo json_encode([
                "status" => false,
                "message" => "Service provider error (Auth)"
            ]);
            exit;
        }

        // SafeHaven API URL
        $url = 'https://api.safehavenmfb.com/vas/pay/utility';

        // Prepare Payload
        // Note: Adjust payload structure based on exact SafeHaven documentation if available.
        // Assuming standard structure based on context requests.
        $payload = [
            "amount" => $amount,
            "mobile" => $phone,
            "billId" => $serviceID, // Or billerCode depending on API
            "subscriberId" => $meterNo,
            // "reference" => $this->generateNineDigitValue() // Optional if API requires manual ref
        ];
        
        // If the API requires billerId/Code specifically:
        if(!empty($billerCode)) {
            $payload['billerId'] = $billerCode;
        }

        $headers = [
            "ClientID: ". $token->client_id,
            "accept: application/json",
            "authorization: Bearer ". $token->access_token,
            "content-type: application/json"
        ];

        // Call External API
        $response = $this->makePostRequest_sh($url, json_encode($payload), $headers);
        $res = json_decode($response);
        
        $date = new DateTime('now', new DateTimeZone('Africa/Lagos'));

        // Handle Response
        // Adjust success check based on actual API response structure (e.g. $res->responseCode == "00" or similar)
        // For now, checking if we have a reference or specific success indicator
        
        if (isset($res->responseCode) && $res->responseCode == "00") { // 00 is common success code
             
            // Construct Transaction ID
            // Format: Reference.Id.DebitRef to ensure uniqueness as requested
            $ref = $res->reference ?? 'REF'.time();
            $id = $res->id ?? 'ID'.rand(1000,9999);
            $debitRef = $res->debitPaymentReference ?? 'DR'.rand(1000,9999);
            
            $tr_id = $ref . "." . $id . "." . $debitRef;
            
            // Truncate if too long (unlikely with this format but good practice for DB limits)
            if(strlen($tr_id) > 250) {
                $tr_id = substr($tr_id, 0, 250);
            }

            $saveData = [
                'user_id' => $userData->user_id,
                'email' => $userData->email,
                'tr_id' => $tr_id,
                'amount' => $amount,
                'tr_type' => 'Electricity', // Corrected from 'Data'
                'phone' => $phone,
                'serviceID' => $serviceID,
                'date' => $date->format('c'),
                'response' => "successful",
                // 'token' => $res->token ?? '' // Save token if returned
            ];

            if ($this->transactionModel->buyData($saveData)) {
                
                // Return success to user
                $saveData['response'] = "Transaction Successful"; 
                 echo json_encode([
                    "status" => true,
                    "message" => "Electricity purchase successful",
                    "data" => $saveData,
                    "token" => $res->token ?? null // Return token to user if available
                ]);
                exit;
            } else {
                 echo json_encode([
                    "status" => false,
                    "message" => "Transaction successful but failed to save record. Contact support."
                ]);
                exit;
            }

        } else {
             // Failed external request
             echo json_encode([
                "status" => false,
                "message" => $res->message ?? "Electricity purchase failed",
                "api_response" => $res // Debug info
            ]);
            exit;
        }
    }

}
