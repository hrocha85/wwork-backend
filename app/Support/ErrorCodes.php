<?php

namespace App\Support;

final class ErrorCodes
{
    public const UNAUTHENTICATED = 'unauthenticated';

    public const AUTH_FAILED = 'auth.failed';

    public const AUTH_MUST_CHANGE_PASSWORD = 'auth.must_change_password';

    public const AUTH_CURRENT_PASSWORD = 'auth.current_password';

    public const AUTH_RESET_EXPIRED = 'auth.reset_expired';

    public const AUTH_RESET_INVALID = 'auth.reset_invalid';

    public const AUTH_PASSWORD_MISMATCH = 'auth.password_mismatch';

    public const REGISTER_EMAIL_TAKEN = 'register.email_taken';

    public const REGISTER_INVALID_TRADE = 'register.invalid_trade';

    public const REGISTER_TERMS_REQUIRED = 'register.terms_required';

    public const REGISTER_PAYMENT_UNAVAILABLE = 'register.payment_unavailable';

    public const ME_INVALID_LOCALE = 'me.invalid_locale';

    public const TEAM_INVITE_FORBIDDEN = 'team.invite_forbidden';

    public const TEAM_EMAIL_ALREADY_INVITED = 'team.email_already_invited';

    public const TEAM_EMAIL_ALREADY_MEMBER = 'team.email_already_member';

    public const TEAM_SEAT_LIMIT = 'team.seat_limit';

    public const TEAM_NOT_OWNER = 'team.not_owner';

    public const TEAM_CANNOT_RATE_OWNER = 'team.cannot_rate_owner';

    public const TEAM_CANNOT_REMOVE_OWNER = 'team.cannot_remove_owner';

    public const TEAM_MEMBER_NOT_FOUND = 'team.member_not_found';

    public const INVITE_ALREADY_ACCEPTED = 'invite.already_accepted';

    public const INVITE_EXPIRED = 'invite.expired';

    public const INVITE_NOT_FOUND = 'invite.not_found';

    public const CLIENT_MISSING_POINT = 'client.missing_point';

    public const CLIENT_FORBIDDEN = 'client.forbidden';

    public const CLIENT_HAS_ACTIVE_VISITS = 'client.has_active_visits';

    public const CLIENT_HAS_INVOICES = 'client.has_invoices';

    public const CLIENT_NOT_FOUND = 'client.not_found';

    public const VISIT_FORBIDDEN = 'visit.forbidden';

    public const VISIT_NOT_ASSIGNEE = 'visit.not_assignee';

    public const VISIT_NOT_FOUND = 'visit.not_found';

    public const VISIT_MISSING_ASSIGNEE = 'visit.missing_assignee';

    public const VISIT_MISSING_POINT = 'visit.missing_point';

    public const VISIT_GOALS_REQUIRED = 'visit.goals_required';

    public const VISIT_INVALID_ASSIGNEE = 'visit.invalid_assignee';

    public const VISIT_ALREADY_DONE = 'visit.already_done';

    public const VISIT_INVOICED = 'visit.invoiced';

    public const VISIT_NOT_OFFERED = 'visit.not_offered';

    public const VISIT_NOT_ACCEPTED = 'visit.not_accepted';

    public const VISIT_INVALID_EVENT = 'visit.invalid_event';

    public const VISIT_MISSING_GPS = 'visit.missing_gps';

    public const VISIT_PHOTO_LIMIT = 'visit.photo_limit';

    public const VISIT_GOAL_NOT_FOUND = 'visit.goal_not_found';

    public const NOT_FOUND = 'not_found';

    public const INVOICE_FORBIDDEN = 'invoice.forbidden';

    public const INVOICE_EMPTY = 'invoice.empty';

    public const INVOICE_CLIENT_MISMATCH = 'invoice.client_mismatch';

    public const INVOICE_VISIT_NOT_DONE = 'invoice.visit_not_done';

    public const INVOICE_VISIT_ALREADY_INVOICED = 'invoice.visit_already_invoiced';

    public const INVOICE_PDF_MISSING = 'invoice.pdf_missing';

    public const INVOICE_SHARE_EXPIRED = 'invoice.share_expired';

    public const AGENDA_INVALID_TOKEN = 'agenda.invalid_token';

    public const AGENDA_MISSING_PLAYER = 'agenda.missing_player';
}
