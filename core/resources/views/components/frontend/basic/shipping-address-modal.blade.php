@props(['countries' => []])
<div class="modal custom--modal fade" id="addressModal" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="modal-title"></h5>
                <button type="button" class="close modal-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <i class="las la-times"></i>
                </button>
                <form method="POST" action="">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>@lang('Title (e.g. Home / Office)')</label>
                                <input type="text" class="form-control form--control" name="label" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Name')</label>
                                <input type="text" class="form-control form--control" name="firstname" required>
                                <input type="hidden" name="lastname" value="">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Mobile')</label>
                                <input type="text" class="form-control form--control" name="mobile" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>@lang('Email (Optional)')</label>
                                <input type="email" class="form-control form--control" name="email">
                            </div>
                        </div>
                        <input type="hidden" name="city" value="">
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="zip" value="">
                        <input type="hidden" name="country" value="Bangladesh">

                        <div class="col-md-12">
                            <div class="form-group mb-0">
                                <label>@lang('Address')</label>
                                <input type="text" class="form-control form--control" name="address" placeholder="@lang('Full Shipping Address')" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn--base w-100 h-45">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
    <script>
        "use strict";
        (function($) {
            let modal = $('#addressModal');
            let action = `{{ route('user.shipping.address.store') }}`;

            $('.newAddress').on('click', function() {
                modal.find('.modal-title').text(`@lang('Add New Shipping Address')`);
                modal.find('form').attr('action', action);
                modal.modal('show');
            });

            $('.editAddress').on('click', function() {
                modal.find('.modal-title').text(`@lang('Update Shipping Address')`);
                let address = $(this).data('resource');

                modal.find('[name=firstname]').val(address.firstname);
                modal.find('[name=lastname]').val(address.lastname);
                modal.find('[name=mobile]').val(address.mobile);
                modal.find('[name=email]').val(address.email);
                modal.find('[name=city]').val(address.city);
                modal.find('[name=state]').val(address.state);
                modal.find('[name=zip]').val(address.zip);
                modal.find('[name=country]').val(address.country);
                modal.find('[name=address]').val(address.address);
                modal.find('[name=label]').val(address.label);

                modal.find('form').attr('action', `${action}/${address.id}`);
                modal.modal('show');
            });

            modal.on('hidden.bs.modal', function() {
                modal.find('form')[0].reset();
            });

        })(jQuery);
    </script>
@endpush
