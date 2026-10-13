<?php
/*
 * Base Controller
 * Loads the models and views and provides helper methods for JSON APIs and PHPMailer email sending.
 */

class Controller
{
    /**
     * Load model
     *
     * @param string $model
     * @return object
     */
    public function model($model)
    {
        $path = __DIR__ . '/../models/' . $model . '.php';
        if (file_exists($path)) {
            require_once $path;
            return new $model();
        }
        throw new Exception("Model {$model} not found at {$path}");
    }

    /**
     * Load view (if needed for non-API routes)
     *
     * @param string $view
     * @param array $data
     * @return void
     */
    public function view($view, $data = [])
    {
        $path = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($path)) {
            require_once $path;
        } else {
            throw new Exception("View {$view} does not exist at {$path}");
        }
    }

    /**
     * Get request body data (JSON or POST/formdata)
     *
     * @return array
     */
    public function getData($strict = false)
    {
        return getData($strict);
    }

    // public function passresetemailer($data, $sub)
    // {
    //     $currentYear = date('Y');
    //     $imagePath = 'https://api.staffroomng.com/uploads/logos/staffroom_logo.png';
    //     $template_file = APPROOT . '/controllers/emails/forget_pass_email.php';

    //     $swap_arr = array(
    //         "IMGSRC" => $imagePath,
    //         "USERNAME" => $data['email'],
    //         "CODE" => $data['otp']
    //     );

    //     if (file_exists($template_file)) {
    //         $message = file_get_contents($template_file);

    //         foreach ($swap_arr as $key => $value) {
    //             if (strlen($key) > 2 && trim($key) != "" && !empty($value)) {
    //                 $message = str_replace("{" . $key . "}", $value, $message);
    //             } else {
    //                 error_log("Password reset email placeholder missing: {$key}");
    //             }
    //         }
    //     } else {
    //         error_log("Password reset email template not found: {$template_file}");
    //         return false;
    //     }

    //     $data['r_email'] = $data['email'];
    //     $success = $this->sendHtmlEmailWithAttachment($data['r_email'], $sub, $message);

    //     if ($success) {
    //         return true;
    //     } else {
    //         return false;
    //     }
    // }
    
      public function getMyJsonID($token, $serverKey)
    {

        return    $JWT_token = JWT::encode($token, $serverKey);
    }



    public function getAuthorizationHeader()
    {
        $headers =  null;
        if (isset($_SERVER['Authorization'])) {

            $headers = trim($_SERVER['Authorization']);
            
        } else if (isset($_SERVER['HTTP_ATHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_ATHORIZATION']);
            
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $request_headers = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
                 
            }
        }
         

        return $headers;
    }

    public function bearer()
    {


        $this->auth_header  = $this->getAuthorizationHeader();


        if (
            $this->auth_header
            &&
            preg_match('#Bearer\s(\S+)#', $this->auth_header, $matches)
        ) {

            return $bearer = $matches['1'];
        }
    }


 

    public function myJsonID($bearer, $serverKey)
    {
        $myJsonID = JWT::decode($bearer, $serverKey);
        if ($myJsonID === 401) {
            return false;
        } else {

            return $myJsonID;
        }
    }



    public function serverKey()
    {
        return   'secret_server_keysa' . date("H");
    }



    //JWT::decode($bearer,'secret_server_key'.date("H"))
    public function RouteProtection(){
  
        $headers =  $this->getAuthorizationHeader();
     
        if (!isset($headers)) {
            $response = ['error' => 'Authorization header missing', 'status' => 401];
          print_r(json_encode($response));
        }else {
             $jwt = str_replace('Bearer ', '', $headers);
        $decoded = $this->myJsonID($jwt, $this->serverKey);
       
        $thisuser = $this->getuserbyid();
        return $thisuser ;
        if (!$decoded) {
            $response = ['error' => 'Invalid token', 'status' => 401];
            print_r($response);
       
        }
       }
       
        
    }

    //echo $bearer;
    public function getuserbyid(){
        $bearer = $this->bearer();
         
        if($bearer){ 
             $userId = $this->myJsonID($bearer,$this->serverKey);
            //  print_r($userId->user_id) ;
            //  exit;
             if(!isset($userId)){
              $response = array(
                
                 'status' => 'false',
                 'message' => 'Oops Something Went Wrong x get!!',
        
              );
              print_r(json_encode($response));
              exit;
          }
          $vb = $this->userModel->getuserbyid($userId->user_id);
          
          if(empty($userId->user_id)) { 
        //   print_r(json_encode($vb));
          $response = array(
                 'status' => 'false',
                 'message' => 'No user with this userID!'
              );
              print_r(json_encode($response));
              
          } else {
            // $response = [
            //   'status' => 'true',
            //   'fullname'=> $vb->full_name,
            //   'email'=> $vb->email,
            //   'tagname'=> $vb->user_tag,
            //   'user_id'=> $vb->veluxite_id,
            // //   'data' => $vb,
            // ];
            //  print_r(json_encode(($vb)));
             return $vb ;
          }
         
         
      
      }
    }

    
    public function welcome_emailer($data, $sub)
    {
        $currentYear = date('Y');
        $imagePath = 'https://api.staffroomng.com/uploads/logos/staffroom_logo.png';
        $template_file = APPROOT . '/controllers/emails/welcome_email.php';

        $swap_arr = array(
            "IMGSRC" => $imagePath,
            "USERNAME" => $data['email'],
            "URL" => $imagePath
        );

        if (file_exists($template_file)) {
            $message = file_get_contents($template_file);

            foreach ($swap_arr as $key => $value) {
                if (strlen($key) > 2 && trim($key) != "" && !empty($value)) {
                    $message = str_replace("{" . $key . "}", htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $message);
                } else {
                    error_log("Welcome email placeholder missing: {$key}");
                }
            }
        } else {
            error_log("Welcome email template not found: {$template_file}");
            return false;
        }

        $data['r_email'] = $data['email'];
        $success = $this->sendHtmlEmailWithAttachment($data['email'], 'Welcome to StaffRoom!', $message);

        if ($success) {
            return true;
        } else {
            return false;
        }
    }

    public function sendOTPEmail($data)
    {
        $currentYear = date('Y');
        $imagePath = 'https://api.staffroomng.com/uploads/logos/staffroom_logo.png';
        $template_file = APPROOT . '/controllers/emails/validate_email.php';

        $swap_arr = array(
            "IMGSRC" => $imagePath,
            "USERNAME" => $data['email'],
            "CODE" => $data['otp']
        );

        if (file_exists($template_file)) {
            $message = file_get_contents($template_file);

            foreach ($swap_arr as $key => $value) {
                if (strlen($key) > 2 && trim($key) != "" && !empty($value)) {
                    $message = str_replace("{" . $key . "}", htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $message);
                } else {
                    error_log("OTP email placeholder missing: {$key}");
                }
            }
        } else {
            error_log("OTP email template not found: {$template_file}");
            return false;
        }

        $data['r_email'] = $data['email'];
        $success = $this->sendHtmlEmailWithAttachment($data['email'], 'VALIDATE YOUR EMAIL - StaffRoom', $message);

        if ($success) {
            return true;
        } else {
            return false;
        }
    }
    
    
    public function passresetemailer($data)
    {
        $currentYear = date('Y');
        $imagePath = 'https://api.staffroomng.com/uploads/logos/staffroom_logo.png';
        $template_file = APPROOT . '/controllers/emails/forget_pass_email.php';

        $swap_arr = array(
            "IMGSRC" => $imagePath,
            "USERNAME" => $data['email'],
            "CODE" => $data['otp']
        );

        if (file_exists($template_file)) {
            $message = file_get_contents($template_file);

            foreach ($swap_arr as $key => $value) {
                if (strlen($key) > 2 && trim($key) != "" && !empty($value)) {
                    $message = str_replace("{" . $key . "}", htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $message);
                } else {
                    error_log("OTP email placeholder missing: {$key}");
                }
            }
        } else {
            error_log("OTP email template not found: {$template_file}");
            return false;
        }

        $data['r_email'] = $data['email'];
        $success = $this->sendHtmlEmailWithAttachment($data['email'], 'PASSWORD RESET - StaffRoom', $message);
        return $success;

        // if ($success) {
        //     return true;
        // } else {
        //     return false;
        // }
    }
    
           public function generateUniqueId()
    {
        // Generate a UUID
        $uuid = uniqid('', true);

        // Get the current timestamp
        $timestamp = microtime(true);

        // Hash them together to ensure uniqueness
        $uniqueId = hash('sha256', $uuid . $timestamp);

        return $uniqueId;
    }
    
        public function generateSixDigitValue()
    {
        $random = mt_rand(0, 999); // Generate a random number between 0 and 999
        $timeString = date('s'); // Get current seconds (or you can use 'u' for microseconds)

        // Concatenate and then take the last 6 digits to ensure it's always 6 digits
        $combined = $random . $timeString;
        return substr(str_pad($combined, 6, '0', STR_PAD_LEFT), -6);
    }

    public function sendHtmlEmailWithAttachment($to, $subject, $message)
    {
        if (!class_exists('PHPMailer')) {
            if (file_exists(__DIR__ . '/../helpers/phpMailer/PHPMailerAutoload.php')) {
                require_once __DIR__ . '/../helpers/phpMailer/PHPMailerAutoload.php';
            } elseif (file_exists(__DIR__ . '/../helpers/phpMailer/class.phpmailer.php')) {
                require_once __DIR__ . '/../helpers/phpMailer/class.phpmailer.php';
                require_once __DIR__ . '/../helpers/phpMailer/class.smtp.php';
            }
        }

        if (!class_exists('PHPMailer')) {
            error_log('Mailer Error: PHPMailer class could not be loaded.');
            return false;
        }

        $mail = new PHPMailer;

        try {
            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = MAIL_HOST;                              // Specify main SMTP server
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_PORT == 465 ? 'ssl' : 'TLS'; // Enable TLS/SSL encryption
            $mail->Port = MAIL_PORT;                              // TCP port to connect to
            $mail->Timeout = 3;                                   // Set connection timeout to 3 seconds

            // Recipients
            $fromEmail = !empty(MAIL_FROM_EMAIL) ? MAIL_FROM_EMAIL : MAIL_USERNAME;
            $mail->setFrom($fromEmail, MAIL_FROM_NAME);
            $mail->addAddress($to);                               // Add a recipient

            $mail->isHTML(true);                                  // Set email format to HTML

            $mail->Subject = $subject;
            $mail->Body    = $message;

            if ($mail->send()) {
                return true;
            } else {
                error_log('Mailer Error: ' . $mail->ErrorInfo);
                return false;
            }
        } catch (Exception $e) {
            error_log('Mailer exception: ' . $e->getMessage());
            return false;
        }
    }
}
?>
