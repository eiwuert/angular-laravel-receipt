@extends('layouts.default')

@section('head')
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>ReceiptClub Management System</title>
    <link href="../css/home-style.css" rel="stylesheet" type="text/css" />
    <script src="../js/jscal2.js" type="text/javascript"></script>
    <script src="../js/lang/en.js" type="text/javascript"></script>
    <link type="text/css" media="screen" rel="stylesheet" href="../css/jscal2.css"/>
    <link type="text/css" media="screen" rel="stylesheet" href="../css/border-radius.css"/>
    <link type="text/css" media="screen" rel="stylesheet" href="../css/gold/gold.css"/>
    <link href="http://www.bootstrap-switch.org/dist/css/bootstrap3/bootstrap-switch.css" rel="stylesheet">
    
@stop

@section('message-handle')
    <!-- Small Nav -->
    <div class="small-nav">
        {{ HTML::linkRoute($baseUrl, 'Dashboard', array()) }}
        <span>&gt;</span>
        Edit Merchant
    </div>
    <!-- End Small Nav -->

    <!-- Message OK -->	
    @if(Session::has('flash_success'))
    <div class="msg msg-ok">
        <p><strong>{{ Session::get('flash_success') }}</strong></p> 
        {{ HTML::linkRoute('merchant-show', 'close', array('id' => $merchant->MerchantID), array('class' => "close")) }}
    </div>
    @endif
    <!-- End Message OK -->		

    <!-- Message Error -->
    @if(Session::has('flash_error'))
        <div class="msg msg-error">
            <p><strong>{{ Session::get('flash_error') }}</strong></p> 
            {{ HTML::linkRoute('merchant-show', 'close', array('id' => $merchant->MerchantID), array('class' => "close")) }}
        </div>
    @endif

    @if($errors->any())
        <div class="msg msg-error">
            <p><strong>{{ $errors->first('Name') }} {{ $errors->first('CountryCode') }} {{$errors->first('message')}}</strong></p>
            {{ HTML::linkRoute('merchant-show', 'close', array('id' => $merchant->MerchantID), array('class' => "close")) }}
        </div>
    @endif

    <!-- End Message Error -->
@stop

@section('content')
    <!-- Box -->
    <div class="box">
        <!-- Box Head -->
        <div class="box-head merchant-editor">
            <h2>Edit Merchant</h2>
            {{ HTML::linkRoute($baseUrl, 'Back to merchant list', array(), array('class' => "btn-back")) }}
        </div>
        <!-- End Box Head -->
        @if (isset($merchant->MerchantID))
            {{ Form::open(array('method' => 'POST', 'route' => array('merchant-edit', $merchant->MerchantID), 'enctype' => 'multipart/form-data')) }}
        @else
            {{ Form::open(array('method' => 'POST', 'route' => array('merchant-add'), 'enctype' => 'multipart/form-data')) }}
        @endif
            <div class="first-block-edit">
                <div class="image-container-edit">
                    @if(isset($merchant->Logo) && ($merchant->Logo != "null"))
                        <img class="logo-merchant" src="{{ $merchant->Logo }}" alt="Smiley face" width="110" height="40">
                        {{ Form::label('Logo', $merchant->LogoName, array('class' => 'logo-name')) }}
                    @else 
                        <img class="logo-merchant" src="" alt="No Logo Image" width="110" height="40">
                    @endif
                    <label class="myFile">
                        <div class="upload-btn" alt=""></div>
                        {{--{{ Form::file('Logo', array('name' => 'Logo', 'size' => "20", 'id' => 'choose-logo-file')) }}--}}
                        {{ Form::file('Logo', array('name' => 'Logo', 'size' => "20", 'id' => 'choose-logo-file', 'onchange' => 'readURL(this);')) }}
                        <script type="text/javascript">
                        function readURL(input) {
                            if (input.files && input.files[0]) {
                                var reader = new FileReader();

                                reader.onload = function (e) {
                                    $('img.logo-merchant').attr('src', e.target.result);
                                };

                                reader.readAsDataURL(input.files[0]);
                            }
                        }
                        </script>
                    </label>
                </div>
                <div class="second-column-edit">
                    {{ Form::label('Name', 'Name') }}
                    {{ Form::text('Name', $merchant->Name, array('name' => 'Name', 'size' => "25", 'class' => 'merchant-name-input', 'style' => 'text-transform: uppercase')) }}
                    {{ Form::label('Address', 'Address') }}
                    {{ Form::text('Address', $merchant->Address, array('name' => 'Address')) }}
                    {{ Form::label('City', 'City') }}
                    {{ Form::text('City', $merchant->City, array('name' => 'City', 'class' => 'small-input')) }}
                    {{ Form::label('CountryCode', 'Country', array('class' => 'country-label')) }}
                    {{ Form::select('CountryCode', $dropdownCountryCode, $merchant->CountryCode, array('class' => 'small-select')); }}
                    {{ Form::label('ZipCode', 'ZipCode', array('class' => 'small-label')) }}
                    {{ Form::text('ZipCode', $merchant->ZipCode, array('name' => 'ZipCode', 'class' => 'small-input')) }}
                    {{ Form::label('State', 'State', array('class' => 'state-label')) }}
                    {{ Form::text('State', $merchant->State, array('name' => 'State', 'class' => 'small-input')) }}
                </div>
                <div class="third-column-edit">
                    {{ Form::label('Website', 'Website') }}
                    {{ Form::text('Website', $merchant->Website, array('name' => 'Website')) }}
                    {{ Form::label('OperationCode', 'Operation Code') }}
                    {{ Form::text('OperationCode', $merchant->OperationCode, array('name' => 'OperationCode')) }}
                    {{ Form::label('PhoneNumber', 'Telephone') }}
                    {{ Form::text('PhoneNumber', $merchant->PhoneNumber, array('name' => 'PhoneNumber')) }}
                    {{ Form::label('Email', 'Email') }}
                    {{ Form::text('Email', $merchant->Email, array('name' => 'Email')) }}
                </div>
                <div class="fourth-column-edit">
                    {{ Form::label('NaicsCode', 'Naics Code') }}
                    {{ Form::text('NaicsCode', $merchant->NaicsCode, array('name' => 'NaicsCode')) }}
                    {{ Form::label('SicCode', 'Sic Code') }}
                    {{ Form::text('SicCode', $merchant->SicCode, array('name' => 'SicCode')) }}
                    {{ Form::label('MccCode', 'Mcc Code') }}
                    {{ Form::text('MccCode', $merchant->MccCode, array('name' => 'MccCode')) }}
                    {{ Form::label('Language', 'Language') }}
                    {{ Form::text('Language', $merchant->Language, array('name' => 'Language', 'class' => 'small-input')) }}
                    {{ Form::label('Searchable', 'Searchable', array('class' => 'searchable-label')) }}
                    <input {{ $merchant->Searchable == 1 ? "disabled" : ""; }}  data-on-color="success" data-off-color="danger" type="checkbox" data-size="mini" data-on-text="ready" data-off-text="draft" name="Searchable" {{ $merchant->Searchable == 1 ? "checked" : ""; }} />
                </div>
            </div>
            <div class="second-block-edit">
                <?php 
                    $count = 0; 
                    $labelCount = 0;
                ?>
                @foreach ($arrayReturn as $key => $findname) 
                    @if ($count % 2 == 0)
                        <div class="single-bot-find-block">
                            <div class="bot-container-edit">
                                {{ Form::label($key, $arrayBotLabel[$labelCount], array('class' => 'title-bot-label')) }}
                                <?php 
                                    $temBotGen = explode(',', $arrayBotGen[$labelCount]);
                                ?>
                                <p><span class="light-green">{{$temBotGen[0]}}</span> - <span class="light-orange">{{$temBotGen[1]}}</span> - <span class="light-blue">AUTO</span></p>
                                <?php 
                                    $colorClass = '';
                                    if (strlen($merchant->$key) >= 5) {
                                        $mysubstr = substr($merchant->$key, 0, 5);
                                        if (strpos($mysubstr,'bot') !== false || strpos($mysubstr,'BOT') !== false) {
                                            $colorClass = ' light-green';
                                        } else if (strpos($mysubstr,'gen') !== false || strpos($mysubstr,'GEN') !== false) {
                                            $colorClass = ' light-orange';
                                        }
                                    }
                                ?>
                                {{ Form::text($key, $merchant->$key, array('name' => $key, 'size' => "20", 'class' => 'check-color' . $colorClass)) }}
                            </div>
                        <?php $labelCount++; ?>
                    @else
                            <div class="find-container-edit">
                                {{ Form::label($key, 'Find Name', array('class' => 'findname-title')) }}
                                @if (count($findname) > 0)
                                    @foreach ($findname as $keyfindname => $singleFindname) 
                                        @if ($keyfindname == 0) 
                                            {{ Form::text($key, $singleFindname, array('name' => $key.'[]', 'size' => "20", 'class' => 'auto-gene-findname')) }}
                                        @else
                                            {{ Form::text($key, $singleFindname, array('name' => $key.'[]', 'size' => "20")) }}
                                        @endif
                                    @endforeach
                                    @for ($i = count($findname); $i < 3; $i++)
                                        {{ Form::text($key, '', array('name' => $key.'[]', 'size' => "20")) }}
                                    @endfor
                                @else
                                    @for ($i = 0; $i < 3; $i++)
                                        @if ($i == 0)
                                            {{ Form::text($key, '', array('name' => $key.'[]', 'size' => "20", 'class' => 'auto-gene-findname')) }}
                                        @else
                                            {{ Form::text($key, '', array('name' => $key.'[]', 'size' => "20")) }}
                                        @endif
                                    @endfor
                                @endif
                            </div>
                            <button type="button" class="add-button-findname" onclick="addField(this)"></button>
                        </div>
                    @endif
                    <?php $count++; ?>
                @endforeach
            </div>
            
            <!-- Form Buttons -->
            <div class="buttons">
                <input type="submit" id="btn-submit" class="button save-merchant" value="Save" />
            </div>
            <!-- End Form Buttons -->
        {{ Form::close() }}
    </div>
    <!-- End Box -->

    <!-- Loading screen -->
    <div id="loading-overlay" class="deleting-receipt" style="display: none">&nbsp;</div>

@stop
