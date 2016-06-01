<!DOCTYPE html>
<html lang="en-US">
	<head>
		<meta charset="utf-8">
	</head>
	<body>
		<p>Dear {{ $name }},</p>
		
		<p>
			Thank you for joining ReceiptClub, we're excited to have you as our newest customer!<br/><br/>
			Please activate your account by following the link below:<br/><br/>
			<a href="{{ $url }}">{{ $url }}</a><br/><br/>
			If you cannot click on it, please copy and paste the whole thing into your browser address bar.<br/><br/>
			You only need to activate your account once, after that you can log into your account by visiting <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>.<br/>
			And entering your details as below:<br/><br/>
			Username: {{ $username }} <br/>
			Password: The password you used to sign up<br/><br/>
			Thank you and welcome,<br/><br/>
			-The ReceiptClub Team.
		</p>
		
		<p>
			P.s.  Do NOT reply to this email as your message will never reach us. 
			If you have a question about our services, please send an email to support@receiptclub.com.
		</p>
	</body>
</html>