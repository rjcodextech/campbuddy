{{--
    Data Request or Complaint Section
    DPDP Act 2023 compliance section for inner pages (About, Guide, Privacy, Terms)
--}}
<section class="data-request-box" aria-labelledby="data-request-heading">
    <div class="data-request-box__inner">
        <div class="data-request-box__content">
            <span class="data-request-box__badge">
                <x-attendee.line-icon name="lock" />
                <span>DPDP Act 2023 &middot; Data Grievance</span>
            </span>
            <h3 id="data-request-heading" class="data-request-box__title">
                Data Request or Complaint
            </h3>
            <p class="data-request-box__desc">
                Need to see, correct, or erase any personal information, or have a privacy grievance? Under India's DPDP Act, we reply to all requests within {{ config('campbuddy.legal.reply_days', 30) }} days.
            </p>
        </div>
        <div class="data-request-box__action">
            <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20Data%20Request%20or%20Complaint" class="btn btn--outline btn--sm" data-track="data_request_email">
                <x-attendee.line-icon name="message-circle" />
                <span>Email Grievance Officer</span>
            </a>
            <span class="data-request-box__email">{{ config('campbuddy.legal.grievance_email') }}</span>
        </div>
    </div>
</section>
