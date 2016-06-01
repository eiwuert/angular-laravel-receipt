@extends('layouts.default')

@section('head')
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>ReceiptClub Management System</title>
    <link href="css/home-style.css" rel="stylesheet" type="text/css" />
    <script src="js/jscal2.js" type="text/javascript"></script>
    <script src="js/lang/en.js" type="text/javascript"></script>
    <link type="text/css" media="screen" rel="stylesheet" href="css/jscal2.css"/>
    <link type="text/css" media="screen" rel="stylesheet" href="css/border-radius.css"/>
    <link type="text/css" media="screen" rel="stylesheet" href="css/gold/gold.css"/>
@stop

@section('message-handle')
    <!-- Small Nav -->
    <div class="small-nav">
        {{ HTML::linkRoute('home', 'Dashboard', array()) }}
        <span>&gt;</span>
        Current Maintenance
    </div>
    <!-- End Small Nav -->

    <!-- Message OK -->	
    @if(Session::has('flash_success'))
    <div class="msg msg-ok">
        <p><strong>{{ Session::get('flash_success') }}</strong></p> 
        {{ HTML::linkRoute('home', 'close', array(), array('class' => 'close')) }}
    </div>
    @endif
    <!-- End Message OK -->		

    <!-- Message Error -->
    @if(Session::has('flash_error'))
        <div class="msg msg-error">
            <p><strong>{{ Session::get('flash_error') }}</strong></p> 
            {{ HTML::linkRoute('home', 'close', array(), array('class' => 'close')) }}
        </div>
    @endif

    @if($errors->any())
        <div class="msg msg-error">
            <p><strong>{{ $errors->first('StartTime') }} {{ $errors->first('EndTime') }} {{$errors->first('message')}}</strong></p> 
            {{ HTML::linkRoute('home', 'close', array(), array('class' => 'close')) }}
        </div>
    @endif

    <!-- End Message Error -->
@stop

@section('content')
	<!-- Box -->
    <div class="box">
        <!-- Box Head -->
        <div class="box-head">
            <h2 class="left">Current Maintenance</h2>
        </div>
        <!-- End Box Head -->	

        <!-- Table -->
        <div class="table">
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <th width="13"><input type="checkbox" class="checkbox" /></th>
                    <th>Reason</th>
                    <th>StartTime (UTC)</th>
                    <th>EndTime (UTC)</th>
                    <th>Added by</th>
                    <th width="110" class="ac">Content Control</th>
                </tr>
                <?php $count = 0; ?>
                @foreach ($maintenanceList as $maintenance)
                    <tr class="<?php echo $count%2 == 0 ? "" : "odd"; ?>">
                        <td><input type="checkbox" class="checkbox" /></td>
                        <?php $maintenanceReason = substr($maintenance->Reason, 0, 14);  ?>
                        <td><h3>{{ $maintenanceReason }}</h3></td>
                        <td><b>{{ $maintenance->StartTime }}</b></td>
                        <td><b>{{ $maintenance->EndTime }}</b></td>
                        <td>{{ $maintenance->Username }}</td>
                        <td>
                            {{ Form::open(array('method' => 'POST', 'route' => array('maintenance-delete', $maintenance->MaintenanceID), "class" => "inline")) }}                       
                                {{ Form::submit('Delete', array('class' => 'ico del')) }}
                            {{ Form::close() }}
                            {{ HTML::linkRoute('maintenance-show', 'Edit', array('id' => $maintenance->MaintenanceID), array('class' => "ico edit")) }}
                        </td>
                    </tr>
                    <?php $count++ ?>
                @endforeach
            </table>


            <!-- Table bottom -->
            <div class="table-bottom">
                <div class="right">
                    <!--<a href="#" class="add-button"><span>Add new Maintenance</span></a>-->
                </div>
            </div>
            <!-- End Table bottoms -->

        </div>
        <!-- Table -->

    </div>
    <!-- End Box -->

    <!-- Box -->
    <div class="box">
        <!-- Box Head -->
        <div class="box-head">
            <h2>Add New Maintenance</h2>
        </div>
        <!-- End Box Head -->

        {{ Form::open(array('url' => 'home')) }}

            <!-- Form -->
            <div class="form" name="clock">	
                <p class="inline-field">
                    <label>Current UTC Time:  <span name="_GMT" id='_GMT' style="color:red;font-weight:bold"></span></label>
                </p>
                <p class="inline-field">
                    <label>StartTime  <span>(Required Field)</span></label>
                    {{ Form::text('StartTime', Input::old('StartTime'), array('id' => 'StartTime', 'size' => "25")) }}
                </p>
                <p class="inline-field">
                    <label>EndTime  <span>(Required Field)</span></label>
                    {{ Form::text('EndTime', Input::old('EndTime'), array('id' => 'EndTime', 'size' => "25")) }}
                </p>
                <p>
                    <span class="req">max 100 characters</span>
                    <label>Maintenance Reason</label>
                    {{ Form::textarea('Reason', Input::old('Reason'), array('id' => 'Reason', 'cols' => "30", 'rows' => "10", 'class' => "field size1")) }}
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