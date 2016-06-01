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
    <link rel="stylesheet" href="http://code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css">
@stop

@section('message-handle')
    <!-- Small Nav -->
    <div class="small-nav">
        {{ HTML::linkRoute($baseUrl, 'Dashboard', array()) }}
        <span>&gt;</span>
        Current Merchant
    </div>
    <!-- End Small Nav -->

    <!-- Message OK -->	
    @if(Session::has('flash_success'))
    <div class="msg msg-ok">
        <p><strong>{{ Session::get('flash_success') }}</strong></p> 
        {{ HTML::linkRoute($baseUrl, 'close', array(), array('class' => 'close')) }}
    </div>
    @endif
    <!-- End Message OK -->		

    <!-- Message Error -->
    @if(Session::has('flash_error'))
        <div class="msg msg-error">
            <p><strong>{{ Session::get('flash_error') }}</strong></p> 
            {{ HTML::linkRoute($baseUrl, 'close', array(), array('class' => 'close')) }}
        </div>
    @endif

    @if($errors->any())
        <div class="msg msg-error">
            <p><strong>{{ $errors->first('StartTime') }} {{ $errors->first('EndTime') }} {{$errors->first('message')}}</strong></p> 
            {{ HTML::linkRoute($baseUrl, 'close', array(), array('class' => 'close')) }}
        </div>
    @endif

    <!-- End Message Error -->
@stop

@section('content')
	<!-- Box -->
    <div class="box">
        <!-- Box Head -->
        <div class="box-head">
            <h2 class="left">Merchant list</h2>
            {{ HTML::linkRoute('merchant-show', '', array('id' => 0), array('class' => 'add-merchant-btn')) }}
            {{ Form::open(array('method' => 'GET', 'route' => array('merchant'), "class" => "inline form-search")) }}   
                {{ Form::text('searchtext', $textSelected, array('size' => "20", 'placeholder' => "Enter some keywords")) }}
                {{ Form::select('letter', $arrayLetter, $letterSelected, array('class' => 'letter-search', 'name' => 'letter')); }}
                {{ Form::select('searchable', $arraySearchable, $searchableSelected, array('class' => 'searchable-search', 'name' => 'searchable')); }}
                <input type="submit" value="Search" class="button">
            {{ Form::close() }}
            
        </div>
        <!-- End Box Head -->	

        <!-- Table -->
        <div class="table search-table-outter">
            <table class="search-table inner" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <th class="content-absolute-left th-absolute-first">ID</th>
                    <th id="title-searchable" class="content-absolute-left th-absolute-second">Status</th>
                    <th class="content-absolute-left th-absolute-third">Merchant Name</th>
                    <th>Logo Image</th>
                    @foreach ($arrayBotGen as $key => $singleBotGen)
                        <?php 
                            $temBotGen = explode(',', $singleBotGen);
                        ?>
                        <th>
                            <p><span class="light-green">{{$temBotGen[0]}}</span> - <span class="light-orange">{{$temBotGen[1]}}</span> - <span class="light-blue">AUTO</span></p>
                            <p class="findname-list-title">Findname</p>
                        </th>
                    @endforeach
                    
                    @foreach ($arrayTitleFields as $key => $singleTitleField)   
                        <th>
                            {{ $singleTitleField }} 
                        </th>
                    @endforeach
                    
                    <th class="content-control th-content">Content Control</th>
                </tr>

                @foreach ($paginator as $k => $merchant)
                    <tr class="<?php echo $k%2 == 0 ? "" : "odd"; ?>">
                        <td class="content-absolute-left td-absolute-first"><h3>{{ $merchant->MerchantID }}</h3></td>
                        <td class="content-absolute-left td-absolute-second">
                            @if ($merchant->Searchable == 0)
                                <div class="non-searchable-tooltip" alt="Non Searchable" width="16" height="16" title="Non Searchable"></div>
                            @else
                                <div class="searchable-tooltip" alt="Searchable" width="16" height="16" title="Searchable"></div>
                            @endif
                        </td>
                        <td title="{{ $merchant->Name }}" class="content-absolute-left td-absolute-third merchant-name-tooltip" id="merchant-name"><b>{{ Str::limit($merchant->Name, 14) }}</b></td>
                        <td>
                            @if(isset($merchant->Logo) && ($merchant->Logo != "null"))
                                <img class="img-logo-tooltip" src="{{ $merchant->Logo }}" alt="Smiley face" width="110" height="40" title="{{ $merchant->LogoName }}">
                            @endif
                        </td>
                        
                        @foreach ($arrayFindnameAndBot as $key => $singleNameAndBot)
                                <?php
                                    $tempBotFindAlgos = explode(',', $singleNameAndBot);
                                    $botTitle = $tempBotFindAlgos[0];
                                    $findTitle = $tempBotFindAlgos[1];
                                    $title = $findTitle.'Title';
                                    $firstTitle = $findTitle.'First';
                                ?>
                        
                                <td class="findname-tooltip" title="{{(isset($merchant->$botTitle) && ($merchant->$botTitle != "") && ($merchant->$botTitle != null)) ? '<p>'.$merchant->$botTitle.'</p><br/>' : ''}}{{(isset($merchant->$title) && ($merchant->$title != "") && ($merchant->$title!= null)) ? '<p>'.$merchant->$title.'</p>' : ''}}">
                                    @if (isset($merchant->$botTitle) && ($merchant->$botTitle != "null")) 
                                    <?php 
                                        $colorClass = '';
                                        if (strlen($merchant->$botTitle) >= 5) {
                                            $mysubstr = substr($merchant->$botTitle, 0, 5);
                                            if (strpos($mysubstr,'bot') !== false || strpos($mysubstr,'BOT') !== false) {
                                                $colorClass = ' light-green';
                                            } else if (strpos($mysubstr,'gen') !== false || strpos($mysubstr,'GEN') !== false) {
                                                $colorClass = ' light-orange';
                                            }
                                        }
                                    ?>
                                    <p><span class="{{$colorClass}}">{{ Str::limit($merchant->$botTitle, 15) }}</span></p>
                                    @endif 
                                    @if(isset($merchant->$findTitle) && ($merchant->$findTitle != "null"))
                                        <p>{{ Str::limit($merchant->$firstTitle, 15) }}</p>
                                    @endif
                                </td>
                        @endforeach
                        
                        @foreach ($arrayEditableFields as $key => $singleField)
                            <td class="field-tooltip" title="{{isset($merchant->$singleField) ? $merchant->$singleField : ''}}">
                                @if(isset($merchant->$singleField) && ($merchant->$singleField != "null"))
                                    @if ($singleField == "CountryCode")
                                        ({{ Str::limit($merchant->$singleField, 15) }}) {{ $merchant->CountryName }}
                                    @else
                                        {{ Str::limit($merchant->$singleField, 15) }}
                                    @endif
                                @endif
                            </td>
                        @endforeach
                        
                        <td class="content-control td-content">
                                
                            {{ Form::open(array('method' => 'POST', 'route' => array('merchant-delete', $merchant->MerchantID), "class" => "inline")) }}                       
                                {{ Form::submit('Delete', array('id' => $merchant->Name, 'class' => 'ico del', 'onClick' => "return showConfirmDeleteModal(this.id)")) }}
                            {{ Form::close() }}
                            
                            
                            {{ HTML::linkRoute('merchant-show', 'Edit', array('id' => $merchant->MerchantID), array('class' => "ico edit")) }}
                        </td>
                    </tr>
                @endforeach
            </table>

        </div>
        <!-- Table -->
        
        <!-- Pagging -->
        <div class="pagging">
            <div class="left">
                Showing {{ ($paginator->getCurrentPage()-1)*15 + 1 }}-{{ $paginator->getCurrentPage() * 15 }} of {{ $paginator->getLastPage() * 15 }}
                <div style="display: none;">
                    {{ $paginator->appends(Input::except('page'))->links(); }}
                </div>
            </div>
            <div class="right on-top">
                <a href="{{ $paginator->getUrl(1) }}" class="item" {{ ($paginator->getCurrentPage() == 1) ? ' disabled onclick="return false"' : '' }}>First</a>
                <a href="{{ $paginator->getUrl($paginator->getCurrentPage()-1) }}" class="item" {{ ($paginator->getCurrentPage() == 1) ? ' disabled onclick="return false"' : '' }}>Previous</a>
                @for ($i = 1; $i <= $paginator->getLastPage(); $i++)
                    <a href="{{ $paginator->getUrl($i) }}" class="item{{ ($paginator->getCurrentPage() == $i) ? ' active' : '' }}">{{ $i }}</a>
                @endfor
                <a href="{{ $paginator->getUrl($paginator->getCurrentPage()+1) }}" class="item" {{ ($paginator->getCurrentPage() == $paginator->getLastPage()) ? ' disabled onclick="return false"' : '' }}>Next</a>
                <a href="{{ $paginator->getUrl($paginator->getLastPage()) }}" class="item" {{ ($paginator->getCurrentPage() == $paginator->getLastPage()) ? ' disabled onclick="return false"' : '' }}>Last</a>
            </div>
            <div class="right">
                <a href="{{ $paginator->getUrl(1) }}" class="item" {{ ($paginator->getCurrentPage() == 1) ? ' disabled onclick="return false"' : '' }}>First</a>
                <a href="{{ $paginator->getUrl($paginator->getCurrentPage()-1) }}" class="item" {{ ($paginator->getCurrentPage() == 1) ? ' disabled onclick="return false"' : '' }}>Previous</a>
                @for ($i = 1; $i <= $paginator->getLastPage(); $i++)
                    <a href="{{ $paginator->getUrl($i) }}" class="item{{ ($paginator->getCurrentPage() == $i) ? ' active' : '' }}">{{ $i }}</a>
                @endfor
                <a href="{{ $paginator->getUrl($paginator->getCurrentPage()+1) }}" class="item" {{ ($paginator->getCurrentPage() == $paginator->getLastPage()) ? ' disabled onclick="return false"' : '' }}>Next</a>
                <a href="{{ $paginator->getUrl($paginator->getLastPage()) }}" class="item" {{ ($paginator->getCurrentPage() == $paginator->getLastPage()) ? ' disabled onclick="return false"' : '' }}>Last</a>
            </div>
        </div>
        <!-- End Pagging -->
    </div>
    <!-- End Box -->
    <script>
        function showConfirmDeleteModal(value) {
            if(confirm("Are you sure you want to remove this merchant: " + value + "?")) {
                return false;
            } else {
                return false;
            }
        }
    </script>
@stop