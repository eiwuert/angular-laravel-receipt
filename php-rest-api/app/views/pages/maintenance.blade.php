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
@stop

@section('message-handle')
    <!-- Small Nav -->
    <div class="small-nav">
        {{ HTML::linkRoute('home', 'Dashboard', array()) }}
        <span>&gt;</span>
        Edit Maintenance
    </div>
    <!-- End Small Nav -->

    <!-- Message OK -->	
    @if(Session::has('flash_success'))
    <div class="msg msg-ok">
        <p><strong>{{ Session::get('flash_success') }}</strong></p> 
        {{ HTML::linkRoute('maintenance-show', 'close', array('id' => $maintenanceObject->MaintenanceID), array('class' => "close")) }}
    </div>
    @endif
    <!-- End Message OK -->		

    <!-- Message Error -->
    @if(Session::has('flash_error'))
        <div class="msg msg-error">
            <p><strong>{{ Session::get('flash_error') }}</strong></p> 
            {{ HTML::linkRoute('maintenance-show', 'close', array('id' => $maintenanceObject->MaintenanceID), array('class' => "close")) }}
        </div>
    @endif

    @if($errors->any())
        <div class="msg msg-error">
            <p><strong>{{ $errors->first('StartTime') }} {{ $errors->first('EndTime') }} {{$errors->first('message')}}</strong></p> 
            {{ HTML::linkRoute('maintenance-show', 'close', array('id' => $maintenanceObject->MaintenanceID), array('class' => "close")) }}
        </div>
    @endif

    <!-- End Message Error -->
@stop

@section('content')
    <!-- Box -->
    <div class="box">
        <!-- Box Head -->
        <div class="box-head">
            <h2>Edit Maintenance</h2>
            {{ HTML::linkRoute('home', 'Back homepage', array(), array('class' => "btn-back")) }}
        </div>
        <!-- End Box Head -->

        {{ Form::open(array('method' => 'POST', 'route' => array('maintenance-edit', $maintenanceObject->MaintenanceID))) }}

            <!-- Form -->
            <div class="form" name="clock">	
                <p class="inline-field">
                    <label>Current UTC Time:  <span name="_GMT" id='_GMT' style="color:red;font-weight:bold"></span></label>
                </p>
                <p class="inline-field">
                    <label>StartTime  <span>(Required Field)</span></label>
                    {{ Form::text('StartTime', $maintenanceObject->StartTime, array('id' => 'StartTime', 'size' => "25")) }}
                </p>
                <p class="inline-field">
                    <label>EndTime  <span>(Required Field)</span></label>
                    {{ Form::text('EndTime', $maintenanceObject->EndTime, array('id' => 'EndTime', 'size' => "25")) }}
                </p>
                <p>
                    <span class="req">max 100 characters</span>
                    <label>Maintenance Reason</label>
                    {{ Form::textarea('Reason', $maintenanceObject->Reason, array('id' => 'Reason', 'cols' => "30", 'rows' => "10", 'class' => "field size1")) }}
                </p>
            </div>
            <!-- End Form -->

            <!-- Form Buttons -->
            <div class="buttons">
                <input type="submit" class="button" value="submit" />
            </div>
            <!-- End Form Buttons -->
        {{ Form::close() }}
    </div>
    <!-- End Box -->
@stop