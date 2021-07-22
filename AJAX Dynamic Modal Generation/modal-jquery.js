 $('#my_modal').on('show.bs.modal', function(e) {
        //Get values from HTML 
        var componentId = $(e.relatedTarget).data('component-id');
        var componentType = $(e.relatedTarget).data('component-type');
        var componentAction = $(e.relatedTarget).data('component-action');

        //Changes buttons based on proposed action
        if (componentAction === 'remove') {
            $("#modal-action-button").html("REMOVE SELECTED");
            $('#modal-action-button').removeClass('btn-inverse-success').addClass('btn-inverse-danger');
        } else {
            $("#modal-action-button").html("ADD SELECTED");
            $('#modal-action-button').removeClass('btn-inverse-danger').addClass('btn-inverse-success');
        }

        $('#modal-action-hidden-input').val(componentAction);
        $('#modal-type-hidden-input').val(componentType);
        $('#modal-component-id-hidden-input').val(componentId);

        $.ajax('ajax-request.php', {
            type: 'POST',
            data: {
                componentId: componentId, componentType: componentType, componentAction: componentAction
            }, 
            success: function (data) {

                //Empty/initialise the table rows
                $("#thetable tr").empty();
              
                //Default roleType
                var roleType = 'info';
                    //Loop Through the data passed back
                    for(var i = 0; i < data.length; i++) {
                        if (data[i].role == 'mentor'){
                            roleType = 'success';
                        } else if(data[i].role == 'arenaroot') {
                            roleType = 'warning';
                        } else if(data[i].role == 'user') {
                            roleType = 'info';
                        }
                     //Generate the table rows
                    $("#thetable").append('<tr class="col-3" style="color:white;"><td>' +  data[i].handlename + '</td><td class="col-4" style="color:gold;">' + data[i].emailaddress + '</td><td class="col-3"><div class="badge badge-outline-' + roleType + '">' + data[i].role + '</div>' + '</td><td class="col-2"><input type="checkbox" value="' + data[i].uid + '" name="selected[]"></td>' + '</tr>');
                }
            },
            dataType:'json'
        });

    });
