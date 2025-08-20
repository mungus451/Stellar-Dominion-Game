<?php
/**
 * PHPMailer - A full-featured email creation and transfer class for PHP
 *
 * @package PHPMailer
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchro.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 * @copyright 2001 - 2020, The PHPMailer team
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @link https://github.com/PHPMailer/PHPMailer
 */

// This is a condensed version of the PHPMailer library for easy inclusion.
// For the full library, please visit the official GitHub repository.

class PHPMailer
{
    public $Host = 'localhost';
    public $Port = 25;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = '';
    public $SMTPAuth = false;
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $CharSet = 'iso-8859-1';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $ErrorInfo = '';

    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $mailHeader = '';

    private $to = [];
    private $cc = [];
    private $bcc = [];
    private $ReplyTo = [];
    private $attachment = [];
    private $CustomHeader = [];
    private $message_type = '';
    private $boundary = [];
    protected $language = [];
    private $error_count = 0;
    private $LE = "\n";
    private $smtp = null;

    public function __construct($exceptions = false)
    {
        // empty constructor
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function addAddress($address, $name = '')
    {
        return $this->addAnAddress('to', $address, $name);
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $this->From = trim($address);
        $this->FromName = $name;
        return true;
    }

    public function addReplyTo($address, $name = '')
    {
        return $this->addAnAddress('Reply-To', $address, $name);
    }

    public function addCC($address, $name = '')
    {
        return $this->addAnAddress('cc', $address, $name);
    }

    public function addBCC($address, $name = '')
    {
        return $this->addAnAddress('bcc', $address, $name);
    }

    public function isHTML($ishtml = true)
    {
        if ($ishtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    protected function addAnAddress($kind, $address, $name = '')
    {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $this->setError('Invalid address: ' . htmlspecialchars($address));
            return false;
        }
        if ($kind != 'Reply-To') {
            if (!isset($this->{$kind})) {
                $this->{$kind} = [];
            }
            $this->{$kind}[] = [$address, $name];
        } else {
            if (!isset($this->ReplyTo[0])) {
                $this->ReplyTo[0] = [$address, $name];
            }
        }
        return true;
    }

    protected function preSend()
    {
        // Implement pre-send logic, like connecting to SMTP
        return true;
    }
    
    protected function postSend()
    {
        // For simplicity, this condensed version uses the native mail() function.
        // A full PHPMailer implementation would handle SMTP connections here.
        $to = '';
        foreach ($this->to as $t) {
            $to .= $t[0] . ', ';
        }
        $to = rtrim($to, ', ');

        $headers = "From: {$this->FromName} <{$this->From}>\r\n";
        $headers .= "Reply-To: {$this->ReplyTo[0][0]}\r\n";
        $headers .= "Content-Type: {$this->ContentType}; charset={$this->CharSet}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if (mail($to, $this->Subject, $this->Body, $headers)) {
            return true;
        } else {
            $this->setError("Message could not be sent.");
            return false;
        }
    }

    protected function setError($msg)
    {
        $this->error_count++;
        $this->ErrorInfo = $msg;
    }
}
