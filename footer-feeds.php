<?php namespace Vanderbilt\EHRPatientFeedExternalModule;

$subscribedFeeds = $module->getSubscribedFeedsForCurrentProject(UNSUBSCRIBED);

if($module->isOnSubscribedPage()){
    $feeds = $subscribedFeeds;
    $emptyMessage = 'This project is currently not subscribed to any feeds.';
}
else{
    $subscribedFeedIds = array_column($subscribedFeeds, 'feed_id');
    $feeds = $module->getFeeds(null, $subscribedFeedIds);

    if(empty($subscribedFeeds)){
        $emptyMessage = "No feeds have been created yet.";
    }
    else{
        $emptyMessage = "You have already subscribed to all feeds.";
    }
}

if(empty($feeds)){
    ?>
    <p><?=$emptyMessage?></p>
    <?php
}

?>

<div class="modal edit-feed" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
            </div>
            <div class="modal-body">
                <label>Please provide a description of what the patients that this feed will contain.  You will likely want to include the rules/logic that will need to be configured for this feed in your EHR.  You can edit this description later.</label>
                <textarea></textarea>
            </div>
            <div class="modal-footer">
                <button class='save'></button>
                <button data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<table></table>

<script>
    $(function(){
        var module = <?=$module->getJavascriptModuleObjectName()?>;
        var isOnSubscribedPage = <?=json_encode($module->isOnSubscribedPage())?>;
        var container = $('#ehr-patient-feed-container')
        
        Object.assign(module, {
            initialize: function(){
                this.initializeTable()
                this.initializeEditFeedButton()
            },
            initializeTable: function(){
                var feeds = <?=json_encode($feeds)?>;
                if(feeds.length === 0){
                    return
                }

                var table = container.find('table').DataTable({
                    searching: false,
                    info: false,
                    paging: false,
                    data: feeds,
                    columns: [
                        {
                            data: 'feed_id',
                            title: 'Feed ID',
                            width: '50px'
                        },
                        {
                            data: 'description',
                            title: 'Description'
                        },
                        {
                            title: 'Actions',
                            width: '125px',
                            render: function(){
                                var updateSubscriptionText
                                if(isOnSubscribedPage){
                                    updateSubscriptionText = "Unsubscribe"
                                }
                                else{
                                    updateSubscriptionText = "Subscribe"
                                }

                                return "<button class='update-subscription'>" + updateSubscriptionText + "</button>" +
                                       "<button class='edit-feed'>Edit Description</button>" +
                                       "<button class='show-url'>Show URL</button>" +
                                       "<button class='view-events'>View Posted Events</button>"
                                       
                            }
                        },
                    ]
                })

                table.on('click', 'button.show-url', function(){
                    var data = module.getDataForButton(this)
                    simpleDialog(data.url, 'Feed URL', null, 800)
                })

                table.on('click', 'button.view-events', function(){
                    var data = module.getDataForButton(this)
                    location.href = <?=json_encode($module->getUrl('view-events.php'))?> + '&feed-id=' + data.feed_id  
                })

                table.on('click', 'button.update-subscription', function(){
                    var warningLanguage
                    var status
                    var successAction

                    if(isOnSubscribedPage){
                        warningLanguage = 'unsubscribe and stop'
                        status = <?=json_encode(UNSUBSCRIBED)?>;
                        successAction = function(){
                            location.reload()
                        }
                    }
                    else{
                        warningLanguage = 'subscribe and start'
                        status = <?=json_encode(SUBSCRIBED)?>;
                        successAction = function(){
                            location.href = <?=json_encode($module->getUrl('subscribed-feeds.php'))?>;
                        }
                    }

                    if(!confirm('Are you sure you want to ' + warningLanguage + ' receiving patients from this feed?')){
                        return
                    }

                    var data = module.getDataForButton(this)
                    data = {
                        feed_id: data.feed_id,
                        status: status
                    }

                    $('body').hide() // poor man's loading indicator
                    $.post(<?=json_encode($module->getUrl('update-subscription.php'))?>, data, function(response){
                        if(response === 'success'){
                            successAction()
                        }
                        else{
                            alert('An error occurred.  See the browser console log for details.')
                            console.log('Error:', response)
                            $('body').show()
                        }
                    })
                })

                module.table = table
            },
            initializeEditFeedButton: function(){
                var editFeedModal = container.find('.modal.edit-feed')
                var feedIdBeingEdited

                var showEditFeedModal = function(title, description, saveButtonText){
                    editFeedModal.find('.modal-title').html(title)
                    editFeedModal.find('.modal-body textarea').html(description)
                    editFeedModal.find('.modal-footer button.save').html(saveButtonText)

                    editFeedModal.modal('show')
                }
                
                container.find('button.edit-feed').click(function(){
                    var data = module.getDataForButton(this)
                    if(!data){
                        feedIdBeingEdited = null
                        showEditFeedModal(
                            "Create Feed",
                            "",
                            "Create Feed & Subscribe"
                        )
                    }
                    else{
                        feedIdBeingEdited = data.feed_id
                        showEditFeedModal(
                            "Edit Feed",
                            data.description,
                            "Save"
                        )
                    }
                })

                editFeedModal.find('button.save').click(function(){
                    var description = editFeedModal.find('textarea').val().trim()
                    if(description === ''){
                        alert("You must enter a description!")
                        return
                    }

                    var data = {
                        description: description
                    }

                    if(feedIdBeingEdited){
                        data.feed_id = feedIdBeingEdited
                    }

                    editFeedModal.hide() // poor man's loading indicator
                    $.post(<?=json_encode($module->getUrl('update-feed.php'))?>, data, function(response){
                        if(response === 'success'){
                            location.reload()
                        }
                        else{
                            alert('An error occurred while creating the feed.  See the browser console log for details.')
                            editFeedModal.show()
                            console.log('Create feed error:', response)
                        }
                    })
                })
            },
            getDataForButton: function(button){
                var tr = $(button).closest('tr')
                if(!tr){
                    return null
                }

                return module.table.row(tr).data()
            }                
        })
        
        module.initialize()
    })
</script>

<?php

require_once __DIR__ . '/footer.php';