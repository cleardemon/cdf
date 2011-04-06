<?php
	/**
	 * Encapsulates sending an email message.
	 * Note that this just uses mail(), nothing special.
	 * @author demon
	 */
	class CDFMailMessage
	{
		// optional, possibly configured by an implemented class
		private $_replyto = null;
		private $_bounce = null;
		private $_signature = "-- \r\nThis e-mail has been automatically generated. Any direct reply may not be delivered.";

		// needed to send
		private $_from = null;
		private $_to = null;
		private $_body = null;
		private $_subject = null;

		//
		// Properties
		//

		final public function setSubject($subject)
		{
			$this->_subject = trim($subject);
		}

		final public function setBody($bodytext)
		{
			$this->_body = trim($bodytext);
		}

		final public function setTo($address, $name = null)
		{
			$this->_to = array(trim($address), $name);
		}

		final public function setFrom($address, $name = null)
		{
			$this->_from = array(trim($address), $name);
		}

		final public function setReplyTo($address, $name = null)
		{
			$this->_replyto = array(trim($address), $name);
		}

		final public function setBounce($address)
		{
			$this->_bounce = trim($address);
		}

		final public function setSignature($sig)
		{
			$this->_signature = trim($sig);
		}

		//
		// Methods
		//

		private function formatAddress($addr)
		{
			return $addr[1] !== null ? sprintf('%s <%s>', $addr[1], $addr[0]) : $addr[0];
		}

		/**
		 * Attempts to send the message to the MTA.
		 * @return bool Result returned by mail()
		 * @throws MailMessageException Thrown when missing required data (subject, to, etc)
		 */
		final public function Send()
		{
			if(strlen($this->_body) < 1 || strlen($this->_subject) < 1 || $this->_to === null || $this->_from === null)
				throw new CDFMailMessageException();

			// compose headers
			$heads = array();
			$heads[] = 'From: ' . $this->formatAddress($this->_from);
			if($this->_replyto !== null)
				$heads[] = 'Reply-To: ' . $this->formatAddress($this->_replyto);
			if($this->_bounce !== null)
				$heads[] = 'Sender: ' . $this->_bounce;
			$heads[] = 'To: ' . $this->formatAddress($this->_to);

			// append signature, format body text
			$body = str_replace("\r\n", "\n", $this->_body);
			if($this->_signature !== null)
				$body .= "\n" . $this->_signature;
			$body = str_replace("\n.", "\n..", $body);
			$body = wordwrap($body, 70);

			// send!
			return mail($this->_to[0], $this->_subject, $body, implode("\r\n", $heads));
		}
	}
