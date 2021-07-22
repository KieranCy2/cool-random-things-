<!-- Calling Modal -->
<a href="#my_modal" data-toggle="modal" data-component-id="<?php echo $example['data'];?>" data-component-type="type1" data-component-action="add">Add Modal</a>
<a href="#my_modal" data-toggle="modal" data-component-id="<?php echo $example['data'];?>" data-component-type="type1" data-component-action="remove">Remove Modal</a>

<!-- Calling Modal -->
<a href="#my_modal" data-toggle="modal" data-component-id="<?php echo $example2['data'];?>" data-component-type="type2" data-component-action="add">Add Modal</a>
<a href="#my_modal" data-toggle="modal" data-component-id="<?php echo $example2['data'];?>" data-component-type="type2" data-component-action="remove">Remove Modal</a>

<!-- Dynamic AJAX Modal -->
  <div class="modal fade" id="my_modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-body">
          <div class="card-body">
            <form action="mentor-user-management.php" name="a" method="POST">
              <div style="inline-block">
                <div class="table-responsive">
                  <table id="thetable" class="table card-table-one mb-5">
                  <tbody style="display: block; height: 200px; overflow-y: scroll">
                  <tr class="d-flex">

                  </tr>
                  </tbody>
                  </table>
                </div>
                <div class="mt-2">
                  <button id="modal-action-button" type="submit" class="btn btn-inverse-success btn-fw"">ADD SELECTED</button>
                  button type="button" class="btn btn-inverse-secondary btn-fw" data-dismiss="modal">Cancel</button>
                </div>
              </div>
              <input id="modal-action-hidden-input" type="hidden" name="action" value="">
              <input id="modal-type-hidden-input" type="hidden" name="type" value="">
              <input id="modal-component-id-hidden-input" type="hidden" name="component-id" value="">
            </form>
        </div>
      </div>
    </div>
  </div>
