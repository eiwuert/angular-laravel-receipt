<!DOCTYPE html>
<html lang="en-US">
	<head>
		<meta charset="utf-8">
	</head>
	<body>
		<p>Dear {{ $name }},<p>

		<p>
			We received a request to reset your password, and you are therefore receiving this email.
			If you did not ask for your password to be reset please contact us immediately at {{ $supportEmail }} so that we may look into rectifying any issues with your account.<br/><br/>
			Otherwise, to reset your account, please click on the link below:<br/><br/>
			<a href="{{ $url }}">{{ $url }}</a><br/><br/>
			If you are unable to click on the link, please copy and paste it into your browser address bar.<br/><br/>
			Please note:  This link will only last for one day, after which you will need to request for your password to be reset again as the above link will expire on {{ $expiryDate }}<br/><br/>
			Thank you for using ReceiptClub,<br/><br/>
			-The ReceiptClub Team
		</p>
		
		<p>
			P.s.  Do NOT reply to this email as your message will never reach us. 
			If you have a question about our services, please send an email to support@receiptclub.com.
		</p>
	</body>
</html>