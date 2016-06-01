<!--
    //  @Author: Vu Quang Son
    //  @DateCreated: 23/10/2014
    /*
    * To change this template, choose Tools | Templates
    * and open the template in the editor.
    */
-->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>ReceiptClub Management System</title>
        <link href="css/login-style.css" rel="stylesheet" type="text/css" />
    </head>

    <body>
        <div id="login-box">
            <center>
                <h2>Welcome to ReceiptClub Management System</h2>

                <!-- FORM Login-->
                {{ Form::open(array('url' => 'login')) }}
                    <table>
                        <tr>
                            <td>
                                <img class="login-logo" src="images/login_avatar.gif"/>
                            </td>
                            <td>
                                <!-- INPUT username-->
                                {{ Form::label('email', 'Email Address') }}
                                {{ Form::text('email', Input::old('email'), array('placeholder' => 'awesome@awesome.com')) }}
                                <!-- INPUT password-->
                                {{ Form::label('password', 'Password') }}
                                {{ Form::password('password') }}
                            </td>
                            <td class="button-block">
                                <!-- SUBMIT -->
                                <input type="image" src="images/login_btn_arr.gif" />
                                <!-- HELP - popup -->
                                <img src="images/login_btn_ask.gif" class="login-help" onclick="alert('Help!\n\nPlease enter your username and password to login into ReceiptClub Management System.')"/>
                            </td>
                        </tr>
                    </table>
                {{ Form::close() }}
                <br/>
                <p class="errors-notice">
                    @if($errors->any())
                        {{$errors->first('messages')}}
                    @endif
                    {{ $errors->first('email') }} {{ $errors->first('password') }}
                    @if(Session::has('flash_error'))
                        {{ Session::get('flash_error') }}
                    @endif
                </p>
                <h3> Notice: Login by the ReceiptClub Admin's account</h3>
            </center>
        </div>
    </body>
</html>
