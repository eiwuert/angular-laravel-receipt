rciSpaApp.directive('popoverMemo', function($timeout, $rootScope){
    return {
        restrict: 'E',
        scope: {
            index: '@',
            currItem: '=',
            currReport: '=',
            editable: '@'
        },
        controller: function ($scope, Restangular) {
            $scope.submitMemo = function(message){
                if (!$scope.editable) {
                    return false;
                }

                var memoData = {
                    "ReportID": $scope.currReport.ReportID,
                    "ItemID": $scope.currItem.ItemID,
                    "Message": message
                }

                if (! $scope.currReport.ReportID) {

                    memoData.SenderType = "Submitter";
                    var dateStr = new Date().toISOString();
                    memoData.CreatedDate = formatDateOrTime(dateStr, 'date');
                    memoData.CreatedTime = formatDateOrTime(dateStr, 'time');
                    memoData.Email = $rootScope.loggedInUser.Email;
                    $scope.currItem.ReportMemos.push(memoData);

                    return true;
                }

                Restangular.one('reports').customPOST(memoData, 'memo').then(function(response) {
                    var newMemo = memoData;
                    newMemo.SenderType = ($scope.currReport.IsSubmitter == 1)? "Submitter":"Approver";
                    newMemo.CreatedDate = response.CreatedDate;
                    newMemo.CreatedTime = response.CreatedTime;
                    newMemo.Email = response.Sender;

                    var prevHeight = $('#rdPopoverMemo' + $scope.index).height();
                    $scope.currItem.ReportMemos.push(newMemo);
                    //Fix bug Popover Box is extended to bottom instead of top
                    $timeout(function(){
                        var target = '#rdPopoverMemo' + $scope.index;
                        var h = $(target).height();
                        if (h > prevHeight) {
                            var top = $(target).css('top');
                            top = top.replace('px', '');
                            top = parseInt(top);
                            $(target).animate({'top': top-(h - prevHeight) + 'px'}, 200);
                        }
                    })
                }, function(response) {
                    if (response.status !== 200) {
                        console.log(response.data.message);
                    }
                });
                $scope.message = '';
                $scope.scrollMessageBox();
            }

            $scope.scrollMessageBox = function() {
                $timeout(function(){
                    var target = '#rdPopoverMemo' + $scope.index +  ' .messages-wrapper';
                    $(target).animate({
                        scrollTop: 9999
                    });
                })
            }

        },
        link: function(scope, element, attrs) {
            element.find('button.close').on('click', function() {
                $(this).parent().hide();
                $('.modal-backdrop').remove();
            });
        },
        template: '<a href="#rdPopoverMemo{{index}}" data-toggle="modal-popover" data-placement="top" class="app-icon icon-memo" ng-class="{\'icon-memo-active\':currItem.ReportMemos.length>0}" style="display: block; margin: 0 auto;" ng-click="scrollMessageBox()"></a>' +
            '<div id="rdPopoverMemo{{index}}" class="rdPopoverMemo popover">' +
                '<div class="arrow"></div>' +
                '<div class="popover-content">' +
                    '<h6>Memo:</h6>' +
                    '<div class="messages-wrapper">' +
                        '<div ng-repeat="memo in currItem.ReportMemos" class="row-message container-fluid" ng-class="{\'no-border\': $first}">' +
                            '<div class="pull-left">[{{memo.CreatedDate}}] {{memo.Email}}: <span ng-class="{\'red\':memo.SenderType==\'Submitter\', \'green\':memo.SenderType==\'Approver\'}">"{{memo.Message}}"</span></div>' +
                            '<div class="pull-right">{{memo.CreatedTime}}</div>' +
                        '</div>' +
                    '</div>' +
                    '<textarea ng-show="editable" type="text" class="input-memo" ng-model="message" maxlength="160"></textarea>' +
                    '<div ng-show="editable"><button class="pull-right btn-add" ng-click="submitMemo(message)">Add</button></div>' +
                '</div>' +
                '<button type="button" class="close">×</button>' +
            '</div>'
    }
});
