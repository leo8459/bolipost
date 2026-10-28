@extends('layouts.cliente-adminlte')

@section('title', 'Mis Solicitudes')

@section('css')
    <style>
        :root {
            --history-navy: #15243d;
            --history-blue: #1f4f94;
            --history-gold: #ffc629;
            --history-cyan: #25d9e8;
            --history-pink: #ff4d8d;
        }

        .history-hero {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            overflow: hidden;
            min-height: 204px;
            margin-bottom: 22px;
            padding: 28px 32px;
            border-radius: 20px;
            color: #fff;
            background: linear-gradient(115deg, #15243d 0%, #1d3f70 62%, #175b71 100%);
            box-shadow: 0 16px 34px rgba(22, 47, 82, .18);
            animation: history-enter .55s ease both;
        }

        .history-hero::before,
        .history-hero::after {
            position: absolute;
            content: "";
            border-radius: 50%;
            pointer-events: none;
        }

        .history-hero::before {
            top: -105px;
            right: 10%;
            width: 270px;
            height: 270px;
            border: 1px solid rgba(255, 255, 255, .14);
            box-shadow: 0 0 0 28px rgba(255, 255, 255, .035), 0 0 0 58px rgba(255, 255, 255, .025);
        }

        .history-hero::after {
            right: 4%;
            bottom: -115px;
            width: 220px;
            height: 220px;
            background: linear-gradient(135deg, rgba(37, 217, 232, .18), rgba(255, 77, 141, .18));
            filter: blur(2px);
        }

        .history-hero__content,
        .history-hero__actions {
            position: relative;
            z-index: 1;
        }

        .history-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: #b5f7fb;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .history-hero__eyebrow i {
            color: var(--history-pink);
            font-size: 1rem;
        }

        .history-hero h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(1.8rem, 3vw, 2.45rem);
            font-weight: 800;
            letter-spacing: -.035em;
        }

        .history-hero p {
            max-width: 620px;
            margin: 8px 0 0;
            color: rgba(255, 255, 255, .78);
            font-size: .98rem;
        }

        .history-hero__actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .history-hero__actions .btn {
            min-height: 43px;
            padding: 10px 17px;
            border-radius: 12px;
            font-weight: 700;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
        }

        .history-hero__actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, .18);
        }

        .history-hero__actions .btn-new {
            border: 0;
            color: #182943;
            background: var(--history-gold);
        }

        .history-hero__actions .btn-back {
            border: 1px solid rgba(255, 255, 255, .35);
            color: #fff;
            background: rgba(255, 255, 255, .08);
        }

        .history-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .history-stat {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            min-height: 102px;
            padding: 18px 20px;
            overflow: hidden;
            border: 1px solid #e7edf5;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 7px 20px rgba(21, 36, 61, .055);
            animation: history-enter .55s ease both;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .history-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 26px rgba(21, 36, 61, .1);
        }

        .history-stat__icon {
            display: grid;
            flex: 0 0 48px;
            width: 48px;
            height: 48px;
            place-items: center;
            border-radius: 14px;
            color: #174782;
            background: #eaf2ff;
            font-size: 1.2rem;
        }

        .history-stat--qr .history-stat__icon {
            color: #8e4d00;
            background: #fff4d7;
        }

        .history-stat--pending .history-stat__icon {
            color: #9f2859;
            background: #fff0f6;
        }

        .history-stat__label {
            color: #68778d;
            font-size: .8rem;
            font-weight: 700;
        }

        .history-stat__value {
            margin-top: 2px;
            color: var(--history-navy);
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .history-panel {
            overflow: hidden;
            border: 1px solid #e5ebf3;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(21, 36, 61, .07);
            animation: history-enter .65s ease both;
        }

        .history-panel__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 22px;
            border-bottom: 1px solid #edf1f6;
        }

        .history-panel__title {
            margin: 0;
            color: var(--history-navy);
            font-size: 1.1rem;
            font-weight: 800;
        }

        .history-panel__subtitle {
            display: block;
            margin-top: 4px;
            color: #748198;
            font-size: .84rem;
        }

        .history-search {
            position: relative;
            width: min(100%, 340px);
        }

        .history-search i {
            position: absolute;
            top: 50%;
            left: 14px;
            color: #8794a8;
            transform: translateY(-50%);
        }

        .history-search input {
            height: 42px;
            padding-left: 39px;
            border: 1px solid #dfe6ef;
            border-radius: 12px;
            box-shadow: none;
        }

        .history-search input:focus {
            border-color: #6798d3;
            box-shadow: 0 0 0 3px rgba(31, 79, 148, .1);
        }

        .history-list {
            display: grid;
            gap: 12px;
            padding: 16px;
        }

        .history-entry {
            display: grid;
            grid-template-columns: minmax(220px, 1.15fr) minmax(180px, .9fr) minmax(155px, .65fr) auto;
            align-items: center;
            gap: 18px;
            padding: 17px 18px;
            border: 1px solid #e9eef5;
            border-radius: 15px;
            background: linear-gradient(115deg, #fff 0%, #fcfdff 100%);
            animation: history-enter .45s ease both;
            transition: border-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        .history-entry:hover {
            transform: translateY(-2px);
            border-color: #c7d8ee;
            box-shadow: 0 10px 22px rgba(29, 63, 112, .08);
        }

        .history-entry__identity {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
        }

        .history-entry__icon {
            display: grid;
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 13px;
            color: #174782;
            background: #eaf2ff;
        }

        .history-entry--qr .history-entry__icon {
            color: #8e4d00;
            background: #fff4d7;
        }

        .history-entry__main {
            min-width: 0;
        }

        .history-entry__code {
            display: block;
            overflow: hidden;
            color: #1a2d49;
            font-size: .96rem;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .history-entry__kind {
            display: inline-flex;
            align-items: center;
            margin-top: 5px;
            padding: 3px 8px;
            border-radius: 999px;
            color: #315581;
            background: #eef4fb;
            font-size: .69rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .history-entry--qr .history-entry__kind {
            color: #895000;
            background: #fff3d4;
        }

        .history-entry__detail {
            min-width: 0;
            color: #596981;
            font-size: .83rem;
            line-height: 1.45;
        }

        .history-entry__detail strong {
            display: block;
            margin-bottom: 3px;
            color: #243752;
            font-size: .83rem;
        }

        .history-entry__detail span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .history-entry__meta {
            display: grid;
            gap: 7px;
            color: #68778d;
            font-size: .8rem;
        }

        .history-entry__price {
            color: #203957;
            font-size: .92rem;
            font-weight: 800;
        }

        .history-entry__actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 7px;
        }

        .history-entry__actions .btn {
            border-radius: 9px;
            font-size: .78rem;
            font-weight: 700;
        }

        .history-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .history-badge--success { color: #176b42; background: #e4f7ed; }
        .history-badge--warning { color: #895000; background: #fff3d4; }
        .history-badge--muted { color: #5d6c80; background: #edf1f5; }
        .history-badge--info { color: #23588e; background: #e8f2ff; }

        .history-entry__note {
            margin-top: 5px;
            color: #8a5a0b;
            font-size: .73rem;
            line-height: 1.35;
        }

        .history-entry__payment {
            margin-top: 7px;
            padding: 5px 8px;
            border-radius: 8px;
            color: #176b42;
            background: #eaf8f0;
            font-size: .73rem;
            font-weight: 700;
        }

        .history-empty {
            padding: 48px 20px;
            text-align: center;
        }

        .history-empty__icon {
            display: grid;
            width: 62px;
            height: 62px;
            margin: 0 auto 14px;
            place-items: center;
            border-radius: 19px;
            color: #245b9c;
            background: #edf4ff;
            font-size: 1.5rem;
        }

        .history-empty h3 {
            margin: 0;
            color: #20334f;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .history-empty p {
            max-width: 470px;
            margin: 7px auto 0;
            color: #728096;
        }

        .history-no-results {
            display: none;
            padding: 25px;
            color: #6d7b90;
            text-align: center;
        }

        @keyframes history-enter {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 991.98px) {
            .history-entry {
                grid-template-columns: minmax(210px, 1fr) minmax(170px, 1fr);
            }

            .history-entry__actions {
                justify-content: flex-start;
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 767.98px) {
            .history-hero {
                min-height: 0;
                align-items: flex-start;
                flex-direction: column;
                padding: 23px 21px;
            }

            .history-hero__actions {
                justify-content: flex-start;
            }

            .history-summary {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .history-stat {
                min-height: 82px;
                padding: 14px 16px;
            }

            .history-panel__head {
                align-items: flex-start;
                flex-direction: column;
                padding: 18px;
            }

            .history-search {
                width: 100%;
            }

            .history-list {
                padding: 10px;
            }

            .history-entry {
                grid-template-columns: minmax(0, 1fr);
                gap: 12px;
                padding: 15px;
            }

            .history-entry__actions {
                grid-column: auto;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .history-hero,
            .history-stat,
            .history-panel,
            .history-entry {
                animation: none;
                transition: none;
            }
        }

        #mis-solicitudes-qr-modal .modal-dialog {
            max-width: 540px;
        }

        #mis-solicitudes-qr-modal .qr-modal-content {
            overflow: hidden;
            border: 1px solid rgba(21, 52, 92, .1);
            border-radius: 20px;
            box-shadow: 0 28px 80px rgba(10, 27, 50, .32);
            animation: qr-modal-arrive .32s cubic-bezier(.2, .8, .2, 1) both;
        }

        #mis-solicitudes-qr-modal .qr-modal-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 19px 22px;
            border-bottom: 1px solid #e8edf4;
            background: linear-gradient(115deg, #fff 0%, #f3f7ff 100%);
        }

        #mis-solicitudes-qr-modal .qr-modal-header__icon {
            display: grid;
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            place-items: center;
            border: 1px solid #dbe7fa;
            border-radius: 14px;
            color: #2457a1;
            background: #eaf2ff;
            font-size: 19px;
        }

        #mis-solicitudes-qr-modal .qr-modal-header__copy {
            min-width: 0;
        }

        #mis-solicitudes-qr-modal .qr-modal-header__eyebrow,
        #mis-solicitudes-qr-modal .qr-payment-summary__label,
        #mis-solicitudes-qr-modal .qr-document-card__eyebrow {
            display: block;
            color: #718098;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .09em;
            line-height: 1.4;
            text-transform: uppercase;
        }

        #mis-solicitudes-qr-modal .qr-modal-title {
            margin: 2px 0 2px;
            color: #182b49;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
        }

        #mis-solicitudes-qr-modal .qr-modal-subtitle {
            color: #728097;
            font-size: 12px;
        }

        #mis-solicitudes-qr-modal .qr-modal-close {
            align-self: flex-start;
            margin: -7px -9px 0 auto;
            padding: 5px 8px;
            color: #78869b;
        }

        #mis-solicitudes-qr-modal .qr-modal-body {
            padding: 22px 24px 18px;
            background: #f5f7fb;
        }

        #mis-solicitudes-qr-modal .qr-payment-summary {
            position: relative;
            overflow: hidden;
            margin-bottom: 14px;
            padding: 14px 18px 13px;
            border: 1px solid #e4eaf2;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 5px 16px rgba(33, 57, 91, .045);
            animation: qr-card-enter .42s .04s ease both;
        }

        #mis-solicitudes-qr-modal .qr-payment-summary::after {
            position: absolute;
            top: -43px;
            right: -27px;
            width: 116px;
            height: 116px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(42, 109, 196, .09), rgba(42, 109, 196, 0) 70%);
            content: "";
            pointer-events: none;
        }

        #mis-solicitudes-qr-modal .qr-payment-summary__amount {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 7px;
            margin-top: 3px;
            color: #204f91;
            line-height: 1;
        }

        #mis-solicitudes-qr-modal .qr-payment-summary__amount small {
            font-size: 14px;
            font-weight: 700;
        }

        #mis-solicitudes-qr-modal .qr-payment-summary__amount strong {
            font-size: 31px;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        #mis-solicitudes-qr-modal .qr-payment-summary__hint {
            margin-top: 8px;
            color: #8a96a9;
            font-size: 11px;
        }

        #mis-solicitudes-qr-modal .qr-status-feedback {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
            margin-bottom: 14px;
            padding: 13px 15px;
            border: 1px solid transparent;
            border-radius: 13px;
            font-size: 13px;
            font-weight: 650;
            line-height: 1.5;
            text-align: left;
            animation: qr-card-enter .38s ease both;
        }

        #mis-solicitudes-qr-modal .qr-status-feedback__icon {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, .8);
            font-size: 15px;
        }

        #mis-solicitudes-qr-modal .qr-status-feedback__copy {
            display: block;
            min-width: 0;
        }

        #mis-solicitudes-qr-modal .qr-status-feedback__heading,
        #mis-solicitudes-qr-modal .qr-status-feedback__detail {
            display: block;
        }

        #mis-solicitudes-qr-modal .qr-status-feedback__heading {
            margin-bottom: 1px;
            font-size: 13px;
            font-weight: 800;
        }

        #mis-solicitudes-qr-modal .qr-status-feedback__detail {
            font-size: 12px;
            font-weight: 500;
            opacity: .88;
        }

        #mis-solicitudes-qr-modal .qr-state-info {
            border-color: #b7e5ef;
            color: #176b7e;
            background: #eaf9fc;
        }

        #mis-solicitudes-qr-modal .qr-state-info::after {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, #24a4bb, transparent);
            content: "";
            animation: qr-progress-sweep 1.8s ease-in-out infinite;
        }

        #mis-solicitudes-qr-modal .qr-state-success {
            border-color: #bde7ca;
            color: #176a39;
            background: linear-gradient(110deg, #effbf3, #e6f8ec);
        }

        #mis-solicitudes-qr-modal .qr-state-warning,
        #mis-solicitudes-qr-modal .qr-state-danger {
            border-color: #f3d69c;
            color: #79530b;
            background: #fff8e8;
        }

        #mis-solicitudes-qr-modal .qr-documents {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        #mis-solicitudes-qr-modal .qr-document-card {
            display: flex;
            min-width: 0;
            flex-direction: column;
            align-items: stretch;
            padding: 15px;
            border: 1px solid #e3eaf2;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 5px 16px rgba(33, 57, 91, .045);
            animation: qr-card-enter .42s ease both;
        }

        #mis-solicitudes-qr-modal .qr-documents .qr-document-card:nth-child(1) {
            animation-delay: .08s;
        }

        #mis-solicitudes-qr-modal .qr-documents .qr-document-card:nth-child(2) {
            animation-delay: .16s;
        }

        #mis-solicitudes-qr-modal .qr-document-card__top {
            display: flex;
            min-width: 0;
            align-items: flex-start;
            gap: 11px;
            margin-bottom: 13px;
        }

        #mis-solicitudes-qr-modal .qr-document-card__icon {
            display: grid;
            flex: 0 0 38px;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 12px;
            color: #2457a1;
            background: #edf3ff;
            font-size: 15px;
        }

        #mis-solicitudes-qr-modal .qr-document-card--invoice .qr-document-card__icon {
            color: #a73b42;
            background: #fff0ef;
        }

        #mis-solicitudes-qr-modal .qr-document-card--invoice.is-issued .qr-document-card__icon {
            color: #16804b;
            background: #eaf8ef;
        }

        #mis-solicitudes-qr-modal .qr-document-card--shipment .qr-document-card__icon {
            color: #16804b;
            background: #eaf8ef;
        }

        #mis-solicitudes-qr-modal .qr-document-card__copy {
            min-width: 0;
            padding-top: 1px;
        }

        #mis-solicitudes-qr-modal .qr-document-card__state {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
            color: #16804b;
            font-size: 10px;
            font-weight: 750;
        }

        #mis-solicitudes-qr-modal .qr-document-card__state i {
            font-size: 10px;
        }

        #mis-solicitudes-qr-modal .qr-document-card__title {
            display: block;
            margin-top: 4px;
            color: #243752;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        #mis-solicitudes-qr-modal .qr-document-card__detail {
            display: block;
            margin-top: 4px;
            color: #7c899b;
            font-size: 11px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        #mis-solicitudes-qr-modal .qr-document-card__download {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            gap: 7px;
            width: 100%;
            margin-top: auto;
            padding: 8px 10px;
            border: 1px solid #d8e2f0;
            border-radius: 9px;
            color: #244f87;
            background: #f7faff;
            font-size: 11px;
            font-weight: 800;
            line-height: 1.35;
            text-align: center;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        #mis-solicitudes-qr-modal .qr-document-card__download:hover,
        #mis-solicitudes-qr-modal .qr-document-card__download:focus {
            transform: translateY(-2px);
            border-color: #b8cbe6;
            color: #173e73;
            background: #edf4ff;
            box-shadow: 0 5px 13px rgba(21, 36, 61, .12);
            text-decoration: none;
        }

        #mis-solicitudes-qr-modal .qr-document-card--invoice .qr-document-card__download {
            border-color: #f1d2d0;
            color: #9d3438;
            background: #fff8f7;
        }

        #mis-solicitudes-qr-modal .qr-document-card--shipment .qr-document-card__download {
            border-color: #cbe8d5;
            color: #167849;
            background: #f4fbf6;
        }

        #mis-solicitudes-qr-modal .qr-documents--single {
            grid-template-columns: minmax(0, 1fr);
        }

        #mis-solicitudes-qr-modal .qr-reference {
            display: inline-flex;
            max-width: 100%;
            align-items: center;
            gap: 7px;
            padding: 7px 11px;
            border: 1px solid #e6ebf2;
            border-radius: 999px;
            color: #758196;
            background: rgba(255, 255, 255, .7);
            font-size: 11px;
            overflow-wrap: anywhere;
        }

        #mis-solicitudes-qr-modal .qr-reference i {
            color: #8093ae;
        }

        #mis-solicitudes-qr-modal .qr-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 22px;
            border-top: 1px solid #e8edf4;
            background: #fff;
        }

        #mis-solicitudes-qr-modal .qr-modal-footer__note {
            color: #8793a5;
            font-size: 10px;
        }

        #mis-solicitudes-qr-modal .qr-modal-footer__button {
            min-width: 86px;
            border: 0;
            border-radius: 9px;
            background: #2457a1;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(36, 87, 161, .18);
        }

        #mis-solicitudes-qr-modal .qr-pending-qr {
            border-color: #e0e8f2 !important;
            border-radius: 14px !important;
            box-shadow: 0 6px 20px rgba(33, 57, 91, .06);
        }

        #mis-solicitudes-qr-modal .qr-pending-qr img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 9px;
        }

        #mis-solicitudes-qr-modal .qr-image-download {
            display: flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 11px 2px 0;
            border: 1px solid #c9d9ef;
            border-radius: 10px;
            color: #214f8a;
            background: #f4f8ff;
            font-size: 12px;
            font-weight: 800;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        #mis-solicitudes-qr-modal .qr-image-download:hover,
        #mis-solicitudes-qr-modal .qr-image-download:focus {
            transform: translateY(-1px);
            border-color: #aec5e5;
            color: #173e73;
            background: #eaf2ff;
            box-shadow: 0 5px 13px rgba(21, 36, 61, .12);
            text-decoration: none;
        }

        #mis-solicitudes-qr-modal .qr-image-note {
            display: block;
            margin: 7px 4px 0;
            color: #8591a2;
            font-size: 10px;
            line-height: 1.4;
        }

        #mis-solicitudes-qr-modal .qr-invoice-editor {
            border-color: #f0dba9 !important;
            border-radius: 14px !important;
            background: #fffdf8;
            box-shadow: 0 5px 16px rgba(33, 57, 91, .045);
            animation: qr-card-enter .38s ease both;
        }

        @keyframes qr-modal-arrive {
            from { opacity: 0; transform: translateY(9px) scale(.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes qr-card-enter {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes qr-progress-sweep {
            from { transform: translateX(-100%); }
            to { transform: translateX(100%); }
        }

        @keyframes qr-success-pop {
            0% { transform: scale(.72); }
            65% { transform: scale(1.16); }
            100% { transform: scale(1); }
        }

        @keyframes qr-success-ring {
            0% { opacity: .55; transform: scale(.72); }
            100% { opacity: 0; transform: scale(1.35); }
        }

        #mis-solicitudes-qr-modal .qr-state-success .qr-status-feedback__icon {
            position: relative;
            animation: qr-success-pop .48s cubic-bezier(.2, .8, .2, 1) both;
        }

        #mis-solicitudes-qr-modal .qr-state-success .qr-status-feedback__icon::after {
            position: absolute;
            inset: -4px;
            border: 1px solid currentColor;
            border-radius: 50%;
            content: "";
            animation: qr-success-ring .65s .08s ease-out both;
        }

        @media (max-width: 575.98px) {
            #mis-solicitudes-qr-modal .modal-dialog {
                margin: 10px;
            }

            #mis-solicitudes-qr-modal .qr-modal-content {
                max-height: calc(100vh - 20px);
            }

            #mis-solicitudes-qr-modal .qr-modal-header {
                gap: 11px;
                padding: 15px 16px;
            }

            #mis-solicitudes-qr-modal .qr-modal-header__icon {
                flex-basis: 40px;
                width: 40px;
                height: 40px;
                border-radius: 12px;
            }

            #mis-solicitudes-qr-modal .qr-modal-title {
                font-size: 16px;
            }

            #mis-solicitudes-qr-modal .qr-modal-body {
                overflow-y: auto;
                padding: 16px 14px 14px;
            }

            #mis-solicitudes-qr-modal .qr-payment-summary__amount strong {
                font-size: 28px;
            }

            #mis-solicitudes-qr-modal .qr-documents {
                grid-template-columns: minmax(0, 1fr);
                gap: 9px;
            }

            #mis-solicitudes-qr-modal .qr-document-card {
                padding: 13px;
            }

            #mis-solicitudes-qr-modal .qr-document-card__top {
                margin-bottom: 10px;
            }

            #mis-solicitudes-qr-modal .qr-modal-footer {
                padding: 11px 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            #mis-solicitudes-qr-modal .qr-modal-content,
            #mis-solicitudes-qr-modal .qr-payment-summary,
            #mis-solicitudes-qr-modal .qr-status-feedback,
            #mis-solicitudes-qr-modal .qr-document-card,
            #mis-solicitudes-qr-modal .qr-invoice-editor,
            #mis-solicitudes-qr-modal .qr-state-info::after,
            #mis-solicitudes-qr-modal .qr-state-success .qr-status-feedback__icon,
            #mis-solicitudes-qr-modal .qr-state-success .qr-status-feedback__icon::after,
            #mis-solicitudes-qr-modal .qr-document-card__download,
            #mis-solicitudes-qr-modal .qr-image-download {
                animation: none;
                transition: none;
            }
        }

        .order-success-modal .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 22px;
            box-shadow: 0 24px 70px rgba(16, 49, 95, .28);
        }

        .order-success-modal .modal-body {
            padding: 34px 30px 30px;
            text-align: center;
        }

        .order-success-modal__icon {
            display: flex;
            width: 78px;
            height: 78px;
            margin: 0 auto 20px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #103f81;
            background: #ffcc29;
            font-size: 36px;
            box-shadow: 0 10px 24px rgba(255, 190, 0, .35);
        }

        .order-success-modal__title {
            margin: 0;
            color: #103f81;
            font-size: 28px;
            font-weight: 800;
        }

        .order-success-modal__message {
            margin: 12px 0 0;
            color: #26384f;
            font-size: 18px;
            line-height: 1.5;
        }

        .order-success-modal__notice {
            margin-top: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            color: #103f81;
            background: #fff7d6;
            font-size: 14px;
            line-height: 1.55;
        }

        .order-success-modal__tracking {
            display: grid;
            gap: 5px;
            margin-top: 14px;
            color: #26384f;
            font-size: 14px;
            line-height: 1.5;
        }

        .order-success-modal__code {
            color: #103f81;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .order-success-modal__share-code {
            display: inline-block;
            margin-top: 4px;
            padding: 7px 10px;
            border-radius: 8px;
            color: #8a4b00;
            background: #fff0a8;
            font-weight: 800;
            text-decoration: underline;
            text-decoration-color: #e08b00;
            text-decoration-thickness: 2px;
            text-underline-offset: 3px;
        }

        .order-success-modal__tracking a {
            color: #103f81;
            font-weight: 700;
            text-decoration: underline;
        }

        .order-success-modal__print-hint {
            margin: 12px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .order-success-modal__actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 22px;
        }

        .order-success-modal__button {
            min-width: 150px;
            padding: 10px 24px;
            border: 0;
            border-radius: 999px;
            color: #fff;
            background: #103f81;
            font-weight: 700;
        }

        .order-success-modal__download {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border: 1px solid #103f81;
            border-radius: 999px;
            color: #103f81;
            font-weight: 700;
        }

        .order-success-modal__download:hover,
        .order-success-modal__download:focus {
            color: #fff;
            background: #103f81;
            text-decoration: none;
        }
    </style>
@endsection

@section('content_header')
    <div class="delivery-history">
        <div class="history-hero">
            <div class="history-hero__content">
                <div class="history-hero__eyebrow"><i class="fas fa-shipping-fast" aria-hidden="true"></i> Delivery Express</div>
                <h1>Mis solicitudes</h1>
                <p>Consulta tus envíos, revisa los pagos QR y encuentra cada comprobante en un solo historial.</p>
            </div>
            <div class="history-hero__actions">
            @if (\App\Support\ServiceAvailability::isEnabled('solicitud'))
                <a href="{{ route('clientes.solicitudes.create') }}" class="btn btn-new">
                    <i class="fas fa-plus mr-1" aria-hidden="true"></i> Nueva solicitud
                </a>
            @endif
                <a href="{{ route('clientes.dashboard') }}" class="btn btn-back">
                    <i class="fas fa-arrow-left mr-1" aria-hidden="true"></i> Panel
                </a>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $deliveryHistoryRows = $solicitudes
            ->map(fn ($solicitud) => (object) [
                'type' => 'shipment',
                'created_at' => $solicitud->created_at,
                'record' => $solicitud,
            ])
            ->concat($pagosQrSinSolicitud->map(fn ($pagoQr) => (object) [
                'type' => 'qr',
                'created_at' => $pagoQr->created_at,
                'record' => $pagoQr,
            ]))
            ->sortByDesc(fn ($row) => $row->created_at?->getTimestamp() ?? 0)
            ->values();
        $qrPendingCount = $pagosQrSinSolicitud->where('estado_pago', 'pendiente')->count();
    @endphp

    @if (session('success'))
        <div class="alert alert-success" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning" role="alert">
            {{ session('warning') }}
        </div>
    @endif

    @if (session('ticket_download_url'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ticketDownload = document.createElement('a');
                ticketDownload.href = @json(session('ticket_download_url'));
                ticketDownload.setAttribute('aria-hidden', 'true');
                ticketDownload.tabIndex = -1;
                ticketDownload.style.position = 'fixed';
                ticketDownload.style.left = '-9999px';
                document.body.appendChild(ticketDownload);
                ticketDownload.click();
                ticketDownload.remove();
            });
        </script>
    @endif

    @if ($qrHistoryWarning)
        <div class="alert alert-warning" role="status">{{ $qrHistoryWarning }}</div>
    @endif

    @if ($pagosQrSinSolicitud->isNotEmpty())
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">Pagos QR sin env&iacute;o registrado</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>C&oacute;digo de pago</th>
                                <th>Importe</th>
                                <th>Estado guardado</th>
                                <th>Fecha</th>
                                <th>Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pagosQrSinSolicitud as $pagoQr)
                                <tr>
                                    <td><strong>{{ $pagoQr->internal_code ?: 'Pago QR' }}</strong></td>
                                    <td>Bs {{ \App\Support\BolivianNumber::format((float) $pagoQr->importe, 2, '.', '') }}</td>
                                    <td>
                                        @php
                                            $estadoPagoQr = strtolower((string) $pagoQr->estado_pago);
                                            $estadoPagoQrLabel = in_array($estadoPagoQr, ['pagado', 'paid', 'success', 'completed', 'approved', 'confirmed'], true)
                                                ? 'Pagado'
                                                : (in_array($estadoPagoQr, ['cancelado', 'cancelled', 'rejected', 'failed', 'expired'], true)
                                                    ? 'Cancelado'
                                                    : 'Pendiente');
                                        @endphp
                                        <span class="badge badge-{{ $estadoPagoQrLabel === 'Pagado' ? 'success' : ($estadoPagoQrLabel === 'Cancelado' ? 'secondary' : 'warning') }}">
                                            {{ $estadoPagoQrLabel }}
                                        </span>
                                    </td>
                                    <td>{{ optional($pagoQr->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary js-consultar-pago-qr" data-qr-attempt="{{ $pagoQr->qr_attempt_id }}" data-qr-amount="{{ number_format((float) $pagoQr->importe, 2, '.', '') }}" data-qr-code="{{ $pagoQr->internal_code }}">
                                            <i class="fas fa-qrcode mr-1" aria-hidden="true"></i>Consultar pago
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="delivery-history">
        <section class="history-summary" aria-label="Resumen de tu actividad">
            <div class="history-stat">
                <div class="history-stat__icon"><i class="fas fa-box-open" aria-hidden="true"></i></div>
                <div>
                    <div class="history-stat__label">Envíos registrados</div>
                    <div class="history-stat__value">{{ $solicitudes->count() }}</div>
                </div>
            </div>

            <div class="history-stat history-stat--pending">
                <div class="history-stat__icon"><i class="fas fa-hourglass-half" aria-hidden="true"></i></div>
                <div>
                    <div class="history-stat__label">Pagos QR pendientes</div>
                    <div class="history-stat__value">{{ $qrPendingCount }}</div>
                </div>
            </div>
        </section>

        <section class="history-panel" aria-labelledby="history-list-title">
            <div class="history-panel__head">
                <div>
                    <h2 class="history-panel__title" id="history-list-title">Actividad reciente</h2>
                    <span class="history-panel__subtitle">Env&iacute;os registrados y pagos QR sin env&iacute;o asociado, ordenados del m&aacute;s reciente al m&aacute;s antiguo.</span>
                </div>
                <label class="history-search mb-0">
                    <span class="sr-only">Buscar por código, servicio u origen</span>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input id="historySearch" type="search" class="form-control" placeholder="Buscar código, servicio, ciudad…" autocomplete="off">
                </label>
            </div>

            @if ($deliveryHistoryRows->isEmpty())
                <div class="history-empty">
                    <div class="history-empty__icon"><i class="fas fa-box-open" aria-hidden="true"></i></div>
                    <h3>Tu historial está listo para empezar</h3>
                    <p>Aqu&iacute; aparecer&aacute;n tus env&iacute;os registrados y los pagos QR que todav&iacute;a no tienen un env&iacute;o vinculado.</p>
                </div>
            @else
                <div class="history-list" id="historyList">
                    @foreach ($deliveryHistoryRows as $row)
                        @if ($row->type === 'shipment')
                            @php
                                $solicitud = $row->record;
                                $estadoNombre = (string) optional($solicitud->estadoRegistro)->nombre_estado;
                                $serviceName = $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? 'Delivery Express');
                                $originName = (string) ($solicitud->origen ?: '-');
                                $destinationName = (string) ($solicitud->destino?->nombre_destino ?? '-');
                                $shipmentStatusClass = strtoupper($estadoNombre) === 'SOLICITUD' ? 'warning' : 'success';
                                $qrPayment = $qrPaymentsBySolicitudId[$solicitud->id] ?? null;
                                $qrPaymentStatus = strtolower((string) ($qrPayment->estado_pago ?? ''));
                                $qrPaymentLabel = in_array($qrPaymentStatus, ['pagado', 'paid', 'success', 'completed', 'approved', 'confirmed'], true)
                                    ? 'Pagado'
                                    : (in_array($qrPaymentStatus, ['cancelado', 'cancelled', 'rejected', 'failed', 'expired'], true)
                                        ? 'Cancelado'
                                        : 'Pendiente');
                                $qrPaymentStatusClass = $qrPaymentLabel === 'Pagado' ? 'success' : ($qrPaymentLabel === 'Cancelado' ? 'muted' : 'warning');
                            @endphp
                            <article
                                class="history-entry"
                                data-history-entry
                                data-search="{{ strtolower(implode(' ', [$solicitud->codigo_solicitud, $serviceName, $originName, $destinationName, $estadoNombre, $qrPayment->internal_code ?? '', $qrPaymentLabel ?? ''])) }}"
                                style="animation-delay: {{ min($loop->index, 8) * 35 }}ms"
                            >
                                <div class="history-entry__identity">
                                    <div class="history-entry__icon"><i class="fas fa-box" aria-hidden="true"></i></div>
                                    <div class="history-entry__main">
                                        <span class="history-entry__code">{{ $solicitud->codigo_solicitud ?: 'Envío registrado' }}</span>
                                        <span class="history-entry__kind">Envío registrado</span>
                                    </div>
                                </div>
                                <div class="history-entry__detail">
                                    <strong>{{ $serviceName }}</strong>
                                    <span><i class="fas fa-route mr-1" aria-hidden="true"></i>{{ $originName }} <i class="fas fa-long-arrow-alt-right mx-1" aria-hidden="true"></i> {{ $destinationName }}</span>
                                    @if ($qrPayment)
                                        <span class="history-entry__payment"><i class="fas fa-qrcode mr-1" aria-hidden="true"></i> Pago QR {{ $qrPaymentLabel }}{{ $qrPayment->internal_code ? ' · ' . $qrPayment->internal_code : '' }} · Bs {{ \App\Support\BolivianNumber::format((float) $qrPayment->importe, 2, '.', '') }}</span>
                                    @endif
                                </div>
                                <div class="history-entry__meta">
                                    <span class="history-badge history-badge--{{ $shipmentStatusClass }}">{{ $estadoNombre !== '' ? $estadoNombre : 'Registrado' }}</span>
                                    @if ($qrPayment)
                                        <span class="history-badge history-badge--{{ $qrPaymentStatusClass }}"><i class="fas fa-qrcode" aria-hidden="true"></i> QR {{ $qrPaymentLabel }}</span>
                                    @endif
                                    <span class="history-entry__price">{{ $solicitud->precio !== null ? 'Bs ' . \App\Support\BolivianNumber::format((float) $solicitud->precio, 2, '.', '') : 'Precio no disponible' }}</span>
                                    <span><i class="far fa-calendar-alt mr-1" aria-hidden="true"></i>{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="history-entry__actions">
                                    <a href="{{ route('clientes.solicitudes.ticket', $solicitud) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i> Comprobante
                                    </a>
                                    @if (!empty($qrAttemptsBySolicitudId[$solicitud->id]))
                                        <button type="button" class="btn btn-sm btn-outline-success js-consultar-pago-qr" data-qr-attempt="{{ $qrAttemptsBySolicitudId[$solicitud->id] }}" data-qr-amount="{{ number_format((float) ($qrPayment->importe ?? 0), 2, '.', '') }}" data-qr-code="{{ $qrPayment->internal_code ?? '' }}">
                                            <i class="fas fa-qrcode mr-1" aria-hidden="true"></i> Consultar pago QR
                                        </button>
                                    @endif
                                </div>
                            </article>
                        @else
                            @php
                                $pagoQr = $row->record;
                                $estadoPagoQr = strtolower((string) $pagoQr->estado_pago);
                                $estadoPagoQrLabel = in_array($estadoPagoQr, ['pagado', 'paid', 'success', 'completed', 'approved', 'confirmed'], true)
                                    ? 'Pagado'
                                    : (in_array($estadoPagoQr, ['cancelado', 'cancelled', 'rejected', 'failed', 'expired'], true)
                                        ? 'Cancelado'
                                        : 'Pendiente');
                                $qrStatusClass = $estadoPagoQrLabel === 'Pagado' ? 'success' : ($estadoPagoQrLabel === 'Cancelado' ? 'muted' : 'warning');
                                $qrCode = (string) ($pagoQr->internal_code ?: 'Pago QR');
                                $qrRoute = implode(' → ', array_filter([
                                    trim((string) $pagoQr->origen),
                                    trim((string) $pagoQr->destino),
                                ], fn ($value) => $value !== ''));
                            @endphp
                            <article
                                class="history-entry history-entry--qr"
                                data-history-entry
                                data-search="{{ strtolower(implode(' ', [$qrCode, $pagoQr->servicio, $pagoQr->origen, $pagoQr->destino, $estadoPagoQrLabel])) }}"
                                style="animation-delay: {{ min($loop->index, 8) * 35 }}ms"
                            >
                                <div class="history-entry__identity">
                                    <div class="history-entry__icon"><i class="fas fa-qrcode" aria-hidden="true"></i></div>
                                    <div class="history-entry__main">
                                        <span class="history-entry__code">{{ $qrCode }}</span>
                                        <span class="history-entry__kind">{{ $estadoPagoQrLabel === 'Pagado' ? 'Pago QR confirmado, registro pendiente' : 'Pago QR pendiente' }}</span>
                                    </div>
                                </div>
                                <div class="history-entry__detail">
                                    <strong>{{ $pagoQr->servicio ?: 'Delivery Express' }}</strong>
                                    <span>{{ $qrRoute !== '' ? $qrRoute : 'Solicitud Delivery Express' }} · {{ $pagoQr->cantidad }} {{ (int) $pagoQr->cantidad === 1 ? 'paquete' : 'paquetes' }}</span>
                                    @if ($estadoPagoQrLabel === 'Pagado' && !\App\Support\ServiceAvailability::isEnabled('solicitud'))
                                        <span class="history-entry__note">Pago confirmado. El registro del envío está temporalmente deshabilitado.</span>
                                    @endif
                                </div>
                                <div class="history-entry__meta">
                                    <span class="history-badge history-badge--{{ $qrStatusClass }}">{{ $estadoPagoQrLabel }}{{ $estadoPagoQrLabel === 'Pagado' ? ' · sin envío' : '' }}</span>
                                    <span class="history-entry__price">Bs {{ \App\Support\BolivianNumber::format((float) $pagoQr->importe, 2, '.', '') }}</span>
                                    <span><i class="far fa-calendar-alt mr-1" aria-hidden="true"></i>{{ optional($pagoQr->created_at)->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="history-entry__actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary js-consultar-pago-qr" data-qr-attempt="{{ $pagoQr->qr_attempt_id }}" data-qr-amount="{{ number_format((float) $pagoQr->importe, 2, '.', '') }}" data-qr-code="{{ $pagoQr->internal_code }}">
                                        <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i> Consultar pago
                                    </button>
                                </div>
                            </article>
                        @endif
                    @endforeach
                </div>
                <div class="history-no-results" id="historyNoResults">No encontramos movimientos que coincidan con tu búsqueda.</div>
            @endif
        </section>
    </div>

    <div class="modal fade" id="mis-solicitudes-qr-modal" tabindex="-1" role="dialog" aria-labelledby="mis-solicitudes-qr-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content qr-modal-content">
                <div class="modal-header qr-modal-header">
                    <div class="qr-modal-header__icon" aria-hidden="true"><i class="fas fa-qrcode"></i></div>
                    <div class="qr-modal-header__copy">
                        <span class="qr-modal-header__eyebrow">Confirmaci&oacute;n de pago</span>
                        <h5 class="modal-title qr-modal-title" id="mis-solicitudes-qr-title">Estado del pago QR</h5>
                        <small class="qr-modal-subtitle">Seguimiento en tiempo real con Facturaci&oacute;n</small>
                    </div>
                    <button type="button" class="close qr-modal-close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body qr-modal-body text-center">
                    <div class="qr-payment-summary">
                        <span class="qr-payment-summary__label">Importe del pago</span>
                        <div class="qr-payment-summary__amount"><small>Bs</small><strong id="mis-solicitudes-qr-importe">0.00</strong></div>
                        <div class="qr-payment-summary__hint"><i class="fas fa-lock mr-1" aria-hidden="true"></i>Pago QR seguro</div>
                    </div>
                    <div id="mis-solicitudes-qr-estado" class="qr-status-feedback qr-state-info" role="status" aria-live="polite">
                        <i class="qr-status-feedback__icon fas fa-circle-notch fa-spin" aria-hidden="true"></i>
                        <span class="qr-status-feedback__copy">Consultando el estado del pago...</span>
                    </div>
                    <div class="qr-documents" aria-live="polite">
                        <div id="mis-solicitudes-qr-factura" class="qr-document-card d-none" role="status"></div>
                        <div id="mis-solicitudes-qr-registro" class="qr-document-card d-none" role="status"></div>
                    </div>
                    <div id="mis-solicitudes-qr-factura-editor" class="qr-invoice-editor d-none text-left border rounded p-3 mb-3">
                        <div class="alert alert-warning py-2">
                            El pago est&aacute; confirmado, pero la factura no pudo emitirse. Revisa tus datos fiscales y vuelve a intentarlo; no se generar&aacute; otro cobro.
                        </div>
                        <form id="mis-solicitudes-qr-factura-form">
                            <div class="form-group">
                                <label for="mis-qr-tipo-documento">Tipo de documento</label>
                                <select id="mis-qr-tipo-documento" name="tipo_documento" class="form-control" required>
                                    @foreach (\App\Models\Cliente::tiposDocumentoIdentidad() as $tipoDocumentoId => $tipoDocumentoLabel)
                                        <option value="{{ $tipoDocumentoId }}">{{ $tipoDocumentoLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-7">
                                    <label for="mis-qr-numero-documento">N&uacute;mero de documento</label>
                                    <input id="mis-qr-numero-documento" name="numero_documento" class="form-control" maxlength="80" required>
                                </div>
                                <div class="form-group col-md-5">
                                    <label for="mis-qr-complemento">Complemento</label>
                                    <input id="mis-qr-complemento" name="complemento_documento" class="form-control" maxlength="30">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mis-qr-razon-social">Raz&oacute;n social</label>
                                <input id="mis-qr-razon-social" name="razon_social" class="form-control" maxlength="255" required>
                            </div>
                            <div class="form-group mb-2">
                                <label for="mis-qr-correo-factura">Correo de factura</label>
                                <input id="mis-qr-correo-factura" name="correo_facturacion" type="email" class="form-control" maxlength="50" required>
                            </div>
                            <div id="mis-solicitudes-qr-factura-error" class="alert alert-danger d-none" role="alert"></div>
                            <button id="mis-solicitudes-qr-factura-submit" type="submit" class="btn btn-warning btn-block">
                                <i class="fas fa-file-invoice mr-1" aria-hidden="true"></i>Guardar datos y reintentar factura
                            </button>
                        </form>
                    </div>
                    <div id="mis-solicitudes-qr-imagen-wrap" class="qr-pending-qr mx-auto mb-3 p-2 border rounded d-none" style="max-width: 300px; background: #fff;">
                        <img id="mis-solicitudes-qr-imagen" class="img-fluid" alt="QR pendiente de pago">
                        <a id="mis-solicitudes-qr-descargar" class="qr-image-download" href="#" download="QR-de-pago.png" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-download" aria-hidden="true"></i>Descargar imagen
                        </a>
                        <small class="qr-image-note">En celular, si la imagen se abre aparte, mantenla presionada para guardarla.</small>
                    </div>
                    <div class="qr-reference"><i class="fas fa-fingerprint" aria-hidden="true"></i><span>Referencia:</span><strong id="mis-solicitudes-qr-codigo" class="font-weight-bold"></strong></div>
                </div>
                <div class="modal-footer qr-modal-footer">
                    <small class="qr-modal-footer__note"><i class="fas fa-shield-alt mr-1" aria-hidden="true"></i>Tu pago se verifica de forma segura</small>
                    <button type="button" class="btn btn-primary qr-modal-footer__button" data-dismiss="modal">Listo</button>
                </div>
            </div>
        </div>
    </div>

    @if (session('pickup_notice'))
        <div class="modal fade order-success-modal" id="orderSuccessModal" tabindex="-1" role="dialog" aria-labelledby="orderSuccessModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="order-success-modal__icon" aria-hidden="true">
                            <i class="fas fa-check"></i>
                        </div>
                        <h2 class="order-success-modal__title" id="orderSuccessModalTitle">¡Muchas gracias por su pedido!</h2>
                        <p class="order-success-modal__message">Enseguida nos comunicaremos con usted.</p>
                        <div class="order-success-modal__notice">
                            <strong>Alerta:</strong><br>
                            {{ session('pickup_notice') }}
                        </div>
                        <div class="order-success-modal__tracking">
                            <strong>Guarde su c&oacute;digo de seguimiento:</strong>
                            <span class="order-success-modal__code">{{ session('pickup_code') }}</span>
                            <span class="order-success-modal__share-code">Comparta este c&oacute;digo con su cliente destinatario para que pueda rastrear el env&iacute;o en Correos de Bolivia.</span>
                            <span>Env&iacute;ele tambi&eacute;n el comprobante para que pueda verificar el costo del servicio. Consulte el rastreo en
                                <a href="https://trackingbo.correos.gob.bo/" target="_blank" rel="noopener noreferrer">TrackingBO</a>.
                                Si paga por QR, conserve tambi&eacute;n el comprobante del pago.
                            </span>
                        </div>
                        <p class="order-success-modal__print-hint">Descargue el comprobante para guardarlo o imprimirlo.</p>
                        <div class="order-success-modal__actions">
                            <a class="order-success-modal__download" href="{{ session('ticket_download_url') }}">
                                <i class="fas fa-file-pdf" aria-hidden="true"></i>Descargar comprobante
                            </a>
                            <button type="button" class="order-success-modal__button" data-dismiss="modal">Entendido</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('js')
    @if (session('pickup_notice'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.jQuery) {
                    window.jQuery('#orderSuccessModal').modal('show');
                }
            });
        </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const historySearch = document.getElementById('historySearch');
            const historyEntries = Array.from(document.querySelectorAll('[data-history-entry]'));
            const noHistoryResults = document.getElementById('historyNoResults');

            historySearch?.addEventListener('input', function () {
                const term = historySearch.value.trim().toLowerCase();
                let visibleEntries = 0;

                historyEntries.forEach(function (entry) {
                    const matches = (entry.dataset.search || '').includes(term);
                    entry.hidden = !matches;
                    if (matches) visibleEntries++;
                });

                if (noHistoryResults) {
                    noHistoryResults.style.display = visibleEntries === 0 ? 'block' : 'none';
                }
            });

            const jquery = window.jQuery;
            if (!jquery) return;

            const modal = jquery('#mis-solicitudes-qr-modal');
            if (!modal.length) return;

            const statusUrl = @json(route('clientes.solicitudes.qr.status'));
            const retryInvoiceUrl = @json(route('clientes.solicitudes.qr.invoice.retry'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const stateElement = document.getElementById('mis-solicitudes-qr-estado');
            const invoiceElement = document.getElementById('mis-solicitudes-qr-factura');
            const requestElement = document.getElementById('mis-solicitudes-qr-registro');
            const documentsElement = modal[0].querySelector('.qr-documents');
            const imageWrap = document.getElementById('mis-solicitudes-qr-imagen-wrap');
            const imageElement = document.getElementById('mis-solicitudes-qr-imagen');
            const imageDownloadLink = document.getElementById('mis-solicitudes-qr-descargar');
            const invoiceEditor = document.getElementById('mis-solicitudes-qr-factura-editor');
            const invoiceForm = document.getElementById('mis-solicitudes-qr-factura-form');
            const invoiceFormError = document.getElementById('mis-solicitudes-qr-factura-error');
            const invoiceFormSubmit = document.getElementById('mis-solicitudes-qr-factura-submit');
            let activeAttempt = '';
            let currentAttempt = '';
            let pollTimer = null;
            let requestInProgress = false;

            function makeIcon(className) {
                const icon = document.createElement('i');
                icon.className = className;
                icon.setAttribute('aria-hidden', 'true');
                return icon;
            }

            function makeDownloadButton(url, label, iconClass) {
                const link = document.createElement('a');
                link.href = url;
                link.className = 'qr-document-card__download';
                link.setAttribute('download', '');
                link.append(makeIcon(iconClass), document.createTextNode(label));
                return link;
            }

            function updateDocumentsLayout() {
                const visibleCards = [invoiceElement, requestElement]
                    .filter(element => !element.classList.contains('d-none'));
                documentsElement.classList.toggle('qr-documents--single', visibleCards.length === 1);
            }

            function setStatus(message, type = 'info') {
                const iconClasses = {
                    info: 'fas fa-circle-notch fa-spin',
                    success: 'fas fa-check',
                    warning: 'fas fa-exclamation-triangle',
                    danger: 'fas fa-times',
                };
                const headings = {
                    info: 'Verificando tu pago',
                    success: '¡Pago confirmado!',
                    warning: 'Atención con este pago',
                    danger: 'No se pudo completar',
                };
                let detail = String(message || '');
                if (type === 'success') detail = detail.replace(/^Pago confirmado[,.\s]*/i, '');

                const icon = makeIcon('qr-status-feedback__icon ' + (iconClasses[type] || iconClasses.info));
                const copy = document.createElement('span');
                copy.className = 'qr-status-feedback__copy';
                const heading = document.createElement('strong');
                heading.className = 'qr-status-feedback__heading';
                heading.textContent = headings[type] || headings.info;
                const description = document.createElement('span');
                description.className = 'qr-status-feedback__detail';
                description.textContent = detail || 'El estado se actualizó correctamente.';
                copy.append(heading, description);
                stateElement.replaceChildren(icon, copy);
                stateElement.className = 'alert alert-' + type + ' qr-status-feedback qr-state-' + type;
            }

            function schedulePoll() {
                if (pollTimer) window.clearTimeout(pollTimer);
                if (activeAttempt) pollTimer = window.setTimeout(() => consultStatus(activeAttempt), 3000);
            }

            function showInvoice(payload) {
                const status = String(payload.invoice_status || '').toUpperCase();
                const signature = JSON.stringify([
                    status,
                    payload.invoice_number || '',
                    payload.invoice_error || '',
                    payload.invoice_download_url || '',
                    Boolean(payload.invoice_retry_allowed),
                ]);
                const labels = {
                    FACTURADA: 'Factura emitida' + (payload.invoice_number ? ' N.º ' + payload.invoice_number : '') + '.',
                    PENDIENTE: 'La factura está pendiente de respuesta fiscal.',
                    PROCESADA: 'La factura está en proceso de emisión.',
                    EMITIENDO: 'La factura se está emitiendo.',
                    EMISION_INCIERTA: 'La emisión de la factura está siendo verificada.',
                    RECHAZADA: 'La factura requiere revisión.',
                    ERROR: 'La factura requiere revisión.',
                    ANULADA: 'La factura figura anulada.',
                };
                if (invoiceElement.dataset.renderKey === signature) {
                    invoiceEditor.classList.toggle('d-none', !payload.invoice_retry_allowed || !payload.paid);
                    return;
                }
                invoiceElement.dataset.renderKey = signature;
                invoiceElement.replaceChildren();
                const invoiceLabel = labels[status] || '';
                if (!invoiceLabel && !payload.invoice_error && !(status === 'FACTURADA' && payload.invoice_download_url)) {
                    invoiceElement.className = 'qr-document-card d-none';
                    updateDocumentsLayout();
                } else {
                    invoiceElement.className = 'qr-document-card qr-document-card--invoice' + (status === 'FACTURADA' ? ' is-issued' : '');

                    const top = document.createElement('div');
                    top.className = 'qr-document-card__top';
                    const iconWrap = document.createElement('div');
                    iconWrap.className = 'qr-document-card__icon';
                    iconWrap.append(makeIcon(status === 'FACTURADA' ? 'fas fa-file-invoice' : 'fas fa-file-alt'));

                    const copy = document.createElement('div');
                    copy.className = 'qr-document-card__copy';
                    const eyebrow = document.createElement('span');
                    eyebrow.className = 'qr-document-card__eyebrow';
                    eyebrow.textContent = 'Factura electrónica';
                    const title = document.createElement('strong');
                    title.className = 'qr-document-card__title';
                    title.textContent = invoiceLabel || (payload.invoice_error ? 'La factura requiere revisión' : 'Factura en proceso');
                    copy.append(eyebrow, title);

                    if (status === 'FACTURADA' && payload.invoice_download_url) {
                        const state = document.createElement('span');
                        state.className = 'qr-document-card__state';
                        state.append(makeIcon('fas fa-check-circle'), document.createTextNode('Lista para descargar'));
                        copy.append(state);
                    } else if (payload.invoice_error) {
                        const detail = document.createElement('span');
                        detail.className = 'qr-document-card__detail';
                        detail.textContent = payload.invoice_error;
                        copy.append(detail);
                    } else if (status === 'FACTURADA') {
                        const detail = document.createElement('span');
                        detail.className = 'qr-document-card__detail';
                        detail.textContent = 'La factura fue emitida, pero el PDF todavía no está disponible.';
                        copy.append(detail);
                    }

                    top.append(iconWrap, copy);
                    invoiceElement.append(top);
                    if (status === 'FACTURADA' && payload.invoice_download_url) {
                        invoiceElement.append(makeDownloadButton(
                            payload.invoice_download_url,
                            'Descargar factura PDF',
                            'fas fa-file-pdf'
                        ));
                    }
                    updateDocumentsLayout();
                }
                invoiceEditor.classList.toggle('d-none', !payload.invoice_retry_allowed || !payload.paid);
                if (payload.invoice_retry_allowed && payload.paid) {
                    const billing = payload.billing_data || {};
                    invoiceForm.elements.namedItem('tipo_documento').value = billing.tipo_documento || '1';
                    invoiceForm.elements.namedItem('numero_documento').value = billing.numero_documento || '';
                    invoiceForm.elements.namedItem('complemento_documento').value = billing.complemento_documento || '';
                    invoiceForm.elements.namedItem('razon_social').value = billing.razon_social || '';
                    invoiceForm.elements.namedItem('correo_facturacion').value = billing.correo_facturacion || '';
                }
            }

            function showRequest(payload) {
                const signature = JSON.stringify([payload.solicitud_codigo || '', payload.ticket_url || '']);
                if (requestElement.dataset.renderKey === signature) return;
                requestElement.dataset.renderKey = signature;
                requestElement.replaceChildren();
                if (!payload.solicitud_codigo && !payload.ticket_url) {
                    requestElement.className = 'qr-document-card d-none';
                    updateDocumentsLayout();
                    return;
                }

                requestElement.className = 'qr-document-card qr-document-card--shipment';
                const top = document.createElement('div');
                top.className = 'qr-document-card__top';
                const iconWrap = document.createElement('div');
                iconWrap.className = 'qr-document-card__icon';
                iconWrap.append(makeIcon('fas fa-box-open'));

                const copy = document.createElement('div');
                copy.className = 'qr-document-card__copy';
                const eyebrow = document.createElement('span');
                eyebrow.className = 'qr-document-card__eyebrow';
                eyebrow.textContent = 'Comprobante de envío';
                const title = document.createElement('strong');
                title.className = 'qr-document-card__title';
                title.textContent = 'Envío registrado';
                copy.append(eyebrow, title);
                if (payload.solicitud_codigo) {
                    const detail = document.createElement('span');
                    detail.className = 'qr-document-card__detail';
                    detail.textContent = 'Código de solicitud: ' + payload.solicitud_codigo;
                    copy.append(detail);
                }
                top.append(iconWrap, copy);
                requestElement.append(top);
                if (payload.ticket_url) {
                    requestElement.append(makeDownloadButton(
                        payload.ticket_url,
                        'Descargar comprobante',
                        'fas fa-receipt'
                    ));
                }
                updateDocumentsLayout();
            }

            async function consultStatus(attemptId) {
                if (!activeAttempt || requestInProgress || activeAttempt !== attemptId) return;
                requestInProgress = true;

                try {
                    const response = await fetch(statusUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new URLSearchParams({ _token: csrfToken, qr_attempt_id: attemptId }),
                    });
                    const payload = await response.json();
                    if (activeAttempt !== attemptId) return;

                    if (payload.qr?.amount != null) document.getElementById('mis-solicitudes-qr-importe').textContent = payload.qr.amount;
                    if (payload.qr?.internal_code) document.getElementById('mis-solicitudes-qr-codigo').textContent = payload.qr.internal_code;
                    showInvoice(payload);
                    showRequest(payload);

                    if (payload.paid || payload.payment_status === 'pagado' || payload.registration_error === 'qr_amount_mismatch') {
                        imageWrap.classList.add('d-none');
                        imageElement.removeAttribute('src');
                        imageDownloadLink.removeAttribute('href');
                    }
                    if (payload.terminal || payload.retry === false) {
                        activeAttempt = '';
                        if (pollTimer) window.clearTimeout(pollTimer);
                    }
                    if (!response.ok) throw new Error(payload.message || 'No se pudo consultar el pago.');

                    if (payload.payment_status === 'pendiente' && payload.qr?.qr_image) {
                        const image = String(payload.qr.qr_image);
                        imageElement.src = image.startsWith('data:image/') || image.startsWith('http://') || image.startsWith('https://')
                            ? image
                            : 'data:image/png;base64,' + image;
                        const imageSource = imageElement.src;
                        const extension = imageSource.match(/^data:image\/(png|jpe?g|webp)/i)?.[1]?.toLowerCase().replace('jpeg', 'jpg')
                            || imageSource.match(/\.(png|jpe?g|webp)(?:[?#]|$)/i)?.[1]?.toLowerCase().replace('jpeg', 'jpg')
                            || 'png';
                        const safeCode = String(document.getElementById('mis-solicitudes-qr-codigo').textContent || 'pago')
                            .replace(/[^a-z0-9_-]/gi, '-');
                        imageDownloadLink.href = imageSource;
                        imageDownloadLink.download = 'QR-' + safeCode + '.' + extension;
                        imageWrap.classList.remove('d-none');
                        setStatus('El pago sigue pendiente. Actualizaremos el estado cada 3 segundos.');
                    } else {
                        imageWrap.classList.add('d-none');
                        imageElement.removeAttribute('src');
                        imageDownloadLink.removeAttribute('href');
                        setStatus(payload.message || 'Estado del pago actualizado.', payload.payment_status === 'cancelado' ? 'warning' : 'success');
                    }

                    if (payload.terminal) {
                        activeAttempt = '';
                        if (pollTimer) window.clearTimeout(pollTimer);
                    }
                } catch (error) {
                    setStatus(error.message || 'No se pudo consultar el pago. Reintentaremos en 3 segundos.', 'warning');
                } finally {
                    requestInProgress = false;
                    schedulePoll();
                }
            }

            document.querySelectorAll('.js-consultar-pago-qr').forEach(button => {
                button.addEventListener('click', function () {
                    activeAttempt = button.dataset.qrAttempt || '';
                    currentAttempt = activeAttempt;
                    if (pollTimer) window.clearTimeout(pollTimer);
                    document.getElementById('mis-solicitudes-qr-importe').textContent = button.dataset.qrAmount || '—';
                    document.getElementById('mis-solicitudes-qr-codigo').textContent = button.dataset.qrCode || '';
                    invoiceElement.replaceChildren();
                    invoiceElement.className = 'qr-document-card d-none';
                    delete invoiceElement.dataset.renderKey;
                    invoiceEditor.classList.add('d-none');
                    invoiceFormError.classList.add('d-none');
                    requestElement.replaceChildren();
                    requestElement.className = 'qr-document-card d-none';
                    delete requestElement.dataset.renderKey;
                    documentsElement.classList.remove('qr-documents--single');
                    imageWrap.classList.add('d-none');
                    imageElement.removeAttribute('src');
                    imageDownloadLink.removeAttribute('href');
                    setStatus('Consultando el estado del pago...', 'info');
                    modal.modal('show');
                    if (activeAttempt) consultStatus(activeAttempt);
                });
            });

            invoiceForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (!currentAttempt || requestInProgress) return;
                requestInProgress = true;
                invoiceFormSubmit.disabled = true;
                invoiceFormError.classList.add('d-none');
                setStatus('Guardando los datos y reintentando la factura. El pago no se volver\u00e1 a cobrar...', 'info');

                try {
                    const formData = new FormData(invoiceForm);
                    formData.set('_token', csrfToken);
                    formData.set('qr_attempt_id', currentAttempt);
                    const response = await fetch(retryInvoiceUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: new URLSearchParams(formData),
                    });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'No se pudo reintentar la factura.');

                    showInvoice(payload);
                    showRequest(payload);
                    setStatus(payload.message || 'Se actualizaron los datos fiscales y se reintent\u00f3 la factura.', 'success');
                    activeAttempt = currentAttempt;
                    schedulePoll();
                } catch (error) {
                    invoiceFormError.textContent = error.message || 'No se pudo reintentar la factura.';
                    invoiceFormError.classList.remove('d-none');
                    setStatus('El pago sigue confirmado. No vuelvas a pagar; puedes corregir los datos e intentarlo nuevamente.', 'warning');
                } finally {
                    requestInProgress = false;
                    invoiceFormSubmit.disabled = false;
                }
            });

            modal.on('hidden.bs.modal', function () {
                activeAttempt = '';
                currentAttempt = '';
                if (pollTimer) window.clearTimeout(pollTimer);
            });
        });
    </script>
@endsection
