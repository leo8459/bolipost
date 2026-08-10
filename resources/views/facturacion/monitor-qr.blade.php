<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitor QR</title>
    <style>
        :root {
            --bg: #edf3fc;
            --panel: rgba(255, 255, 255, 0.92);
            --line: rgba(24, 58, 116, 0.1);
            --text: #17315c;
            --muted: #6782aa;
            --accent: #ffcb32;
            --accent-strong: #f4b700;
            --success: #22a25f;
            --warning: #e68426;
            --shadow: 0 24px 60px rgba(23, 49, 92, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(255, 203, 50, 0.16), transparent 18%),
                radial-gradient(circle at bottom right, rgba(66, 133, 244, 0.10), transparent 22%),
                linear-gradient(180deg, #f8fbff, var(--bg));
        }
        .screen {
            min-height: 100vh;
            display: grid;
            grid-template-rows: 1fr;
            padding: 18px;
        }
        .status {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }
        .status__dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: var(--success);
            box-shadow: 0 0 0 8px rgba(34, 162, 95, 0.12);
        }
        .content {
            min-height: 0;
            display: grid;
            place-items: center;
        }
        .state {
            width: min(100%, 1280px);
            min-height: clamp(520px, 72vh, 980px);
            display: grid;
            border-radius: 32px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,251,255,.95));
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }
        .ads, .qr, .terminal, .preview {
            min-height: 100%;
            padding: 36px;
        }
        .ads {
            display: grid;
            place-items: center;
        }
        .qr__title, .terminal__title, .preview__title {
            margin: 0;
            font-size: 64px;
            line-height: .96;
            letter-spacing: -.05em;
            font-weight: 900;
        }
        .qr__text, .terminal__text, .preview__text {
            margin: 0;
            max-width: 560px;
            font-size: 21px;
            line-height: 1.5;
            color: var(--muted);
        }
        .ads__qr-shell {
            width: min(100%, 560px);
            aspect-ratio: 1 / 1;
            position: relative;
            padding: 28px;
            border-radius: 40px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.18), transparent 36%),
                linear-gradient(180deg, #ffffff, #f5f9ff);
            border: 1px solid rgba(214, 226, 244, 0.9);
            box-shadow: 0 24px 50px rgba(23, 49, 92, 0.10);
        }
        .ads__qr-shell::before,
        .ads__qr-shell::after {
            content: "";
            position: absolute;
            width: 60px;
            height: 60px;
            border: 5px solid #ffcf3f;
            filter: drop-shadow(0 0 12px rgba(255, 203, 50, 0.45));
        }
        .ads__qr-shell::before {
            top: 16px;
            left: 16px;
            border-right: 0;
            border-bottom: 0;
            border-radius: 26px 0 0 0;
        }
        .ads__qr-shell::after {
            top: 16px;
            right: 16px;
            border-left: 0;
            border-bottom: 0;
            border-radius: 0 26px 0 0;
        }
        .ads__qr-shell-inner {
            position: relative;
            width: 100%;
            height: 100%;
            border-radius: 32px;
            border: 2px dashed rgba(118, 154, 255, 0.6);
            background:
                linear-gradient(180deg, rgba(255,255,255,.95), rgba(243,248,255,.95));
            display: grid;
            place-items: center;
            text-align: center;
            padding: 28px;
        }
        .ads__qr-shell-inner::before,
        .ads__qr-shell-inner::after {
            content: "";
            position: absolute;
            width: 60px;
            height: 60px;
            border: 5px solid #ffcf3f;
            filter: drop-shadow(0 0 12px rgba(255, 203, 50, 0.45));
        }
        .ads__qr-shell-inner::before {
            bottom: -14px;
            left: -14px;
            border-right: 0;
            border-top: 0;
            border-radius: 0 0 0 26px;
        }
        .ads__qr-shell-inner::after {
            bottom: -14px;
            right: -14px;
            border-left: 0;
            border-top: 0;
            border-radius: 0 0 26px 0;
        }
        .ads__qr-icon {
            width: 140px;
            height: 140px;
            display: grid;
            place-items: center;
            color: #c0cce3;
        }
        .ads__qr-icon svg {
            width: 104px;
            height: 104px;
        }
        .ads__animation,
        .terminal__animation {
            width: min(100%, 320px);
            aspect-ratio: 1 / 1;
            display: grid;
            place-items: center;
            margin-inline: auto;
        }
        .ads__animation dotlottie-wc,
        .terminal__animation dotlottie-wc {
            display: block;
            width: 100%;
            height: 100%;
        }
        .ads__qr-caption {
            margin: 2px 0 0;
            font-size: 18px;
            font-weight: 800;
            color: #2450b4;
        }
        .ads__note,
        .terminal__note,
        .preview__note {
            margin: 0;
            max-width: 520px;
            font-size: 15px;
            line-height: 1.7;
            color: var(--muted);
            text-align: center;
        }
        .preview {
            display: grid;
            gap: 26px;
        }
        .preview__hero {
            display: grid;
            justify-items: center;
            gap: 18px;
            text-align: center;
            padding: 12px 0 6px;
        }
        .preview__hero-icon {
            width: 112px;
            height: 112px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #f0b323;
            background:
                radial-gradient(circle at top, rgba(255,250,240,.98), rgba(255,242,214,.92)),
                linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,245,225,.92));
            border: 1px solid rgba(240, 179, 35, 0.16);
            box-shadow: 0 18px 38px rgba(240, 179, 35, 0.12);
            font-size: 2.2rem;
        }
        .preview__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #2b58c5;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .preview__title {
            font-size: 64px;
        }
        .preview__text {
            max-width: 760px;
            text-align: center;
        }
        .preview__panel {
            padding: 26px 28px;
            border-radius: 26px;
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(246,250,255,.94));
            border: 1px solid rgba(198, 218, 247, 0.85);
            box-shadow: 0 18px 40px rgba(23,49,92,.06);
        }
        .preview__section-title {
            margin: 0;
            color: var(--text);
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .preview__section-accent {
            width: 210px;
            height: 4px;
            margin-top: 12px;
            border-radius: 999px;
            background: linear-gradient(90deg, #2d62ff 0%, #376ef7 72%, rgba(55, 110, 247, 0.10) 100%);
        }
        .preview__facts-shell {
            margin-top: 22px;
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,251,255,.94));
            border: 1px solid rgba(210, 224, 247, 0.9);
            overflow: hidden;
        }
        .preview__facts {
            display: grid;
            gap: 0;
        }
        .preview__fact {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr);
            gap: 18px;
            align-items: center;
            padding: 24px 24px;
            border-bottom: 1px solid rgba(214, 226, 244, 0.95);
        }
        .preview__fact:last-child {
            border-bottom: 0;
        }
        .preview__fact-icon {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #f0b323;
            background: linear-gradient(180deg, #fff7e6 0%, #fffdf7 100%);
            box-shadow: inset 0 0 0 1px rgba(240, 179, 35, 0.12);
            font-size: 1.45rem;
        }
        .preview__fact-copy {
            display: grid;
            gap: 10px;
        }
        .preview__fact dt {
            color: #6782aa;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin: 0;
        }
        .preview__fact dd {
            margin: 0;
            color: var(--text);
            font-size: 32px;
            line-height: 1.12;
            letter-spacing: -.03em;
            font-weight: 800;
        }
        .preview__items-panel {
            display: grid;
            gap: 18px;
        }
        .preview__items-head {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 14px;
            align-items: center;
        }
        .preview__items-head-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #f0b323;
            background: linear-gradient(180deg, #fff7e6 0%, #fffdf7 100%);
            box-shadow: inset 0 0 0 1px rgba(240, 179, 35, 0.12);
            font-size: 1.35rem;
        }
        .preview__items-head h3 {
            margin: 0;
            color: var(--text);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .preview__items-head span {
            color: #5f78a0;
            font-size: 15px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(244,247,255,.95), rgba(238,244,255,.9));
            border: 1px solid rgba(210, 224, 247, 0.95);
        }
        .preview__items-list {
            display: grid;
            gap: 0;
        }
        .preview__item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding: 18px 6px 18px 0;
            border-bottom: 1px dashed rgba(205, 220, 242, 0.95);
        }
        .preview__item:last-child {
            border-bottom: 0;
        }
        .preview__item strong {
            display: block;
            font-size: 24px;
            line-height: 1.15;
            letter-spacing: -.03em;
        }
        .preview__item p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.45;
        }
        .preview__amount {
            color: var(--text);
            font-size: 24px;
            font-weight: 900;
            white-space: nowrap;
        }
        .preview__summary {
            display: grid;
            gap: 20px;
        }
        .preview__summary-card {
            display: grid;
            grid-template-columns: 108px minmax(0, 1fr) 136px;
            gap: 24px;
            align-items: center;
            padding: 28px 24px;
            border-radius: 22px;
            background:
                radial-gradient(circle at center right, rgba(208, 242, 229, 0.36), transparent 36%),
                linear-gradient(180deg, rgba(245,255,251,.98), rgba(239,251,246,.96));
            border: 1px solid rgba(173, 230, 207, 0.95);
        }
        .preview__summary-badge {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #0a8e58;
            background: radial-gradient(circle at top, rgba(225, 255, 244, 0.96), rgba(205, 244, 228, 0.92));
            border: 1px solid rgba(151, 223, 192, 0.95);
            font-size: 2.4rem;
            box-shadow: inset 0 0 0 1px rgba(10, 142, 88, 0.05);
        }
        .preview__summary-copy {
            display: grid;
            gap: 10px;
        }
        .preview__summary-copy small {
            color: var(--text);
            font-size: 16px;
            font-weight: 700;
        }
        .preview__summary-copy strong {
            color: #0b8c54;
            font-size: 72px;
            line-height: .92;
            letter-spacing: -.06em;
            font-weight: 900;
        }
        .preview__summary-copy p {
            margin: 0;
            color: #4a6e82;
            font-size: 18px;
        }
        .preview__summary-art {
            width: 128px;
            height: 128px;
            border-radius: 22px;
            position: relative;
            background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(244,255,250,.88));
            box-shadow: 0 18px 34px rgba(51, 173, 125, 0.10);
        }
        .preview__summary-art::before,
        .preview__summary-art::after {
            content: "";
            position: absolute;
            border-radius: 16px;
        }
        .preview__summary-art::before {
            inset: 14px 20px 18px 18px;
            background: #ffffff;
            box-shadow: 0 18px 32px rgba(23,49,92,.08);
        }
        .preview__summary-art::after {
            width: 44px;
            height: 44px;
            right: 10px;
            bottom: 10px;
            background: radial-gradient(circle at top, #5de0a1, #26b36c);
            clip-path: polygon(18% 53%, 31% 40%, 45% 55%, 71% 27%, 82% 38%, 46% 76%);
        }
        .preview__summary-lines {
            position: absolute;
            left: 34px;
            top: 28px;
            width: 46px;
            height: 36px;
            z-index: 1;
        }
        .preview__summary-lines span {
            display: block;
            height: 6px;
            margin-bottom: 7px;
            border-radius: 999px;
            background: #d7e6f5;
        }
        .preview__summary-lines span:nth-child(1) { width: 100%; }
        .preview__summary-lines span:nth-child(2) { width: 76%; }
        .preview__summary-lines span:nth-child(3) { width: 58%; }
        .preview__note {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            max-width: none;
            padding: 18px 20px;
            text-align: left;
            font-size: 16px;
            color: #2d58b8;
            border-radius: 16px;
            background: linear-gradient(180deg, rgba(244,247,255,.96), rgba(238,243,255,.92));
            border: 1px solid rgba(203, 218, 247, 0.95);
        }
        .preview__note i {
            font-size: 1.2rem;
            line-height: 1;
            margin-top: 2px;
        }
        .flow__copy {
            display: grid;
            justify-items: center;
            gap: 12px;
            animation: monitorCopyFade .55s ease;
        }
        .flow__status {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.02em;
            color: #2450b4;
            animation: monitorPulse 1.8s ease-in-out infinite;
        }
        @keyframes monitorCopyFade {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes monitorPulse {
            0%, 100% {
                opacity: .68;
            }
            50% {
                opacity: 1;
            }
        }
        .qr {
            display: grid;
            grid-template-columns: minmax(360px, 520px) minmax(320px, 560px);
            gap: 42px;
            align-items: center;
            justify-content: center;
        }
        .qr__visual {
            display: grid;
            place-items: center;
            min-height: 520px;
            padding: 32px;
            border-radius: 40px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.22), transparent 28%),
                linear-gradient(180deg, #ffffff, #f3f8ff);
            border: 1px solid var(--line);
            box-shadow: 0 24px 48px rgba(23,49,92,.09);
        }
        .qr__visual img {
            display: block;
            width: 100%;
            max-width: 420px;
            height: auto;
            padding: 18px;
            border-radius: 30px;
            background: #fff;
            box-shadow: 0 24px 44px rgba(23,49,92,.14);
        }
        .qr__empty {
            color: var(--muted);
            font-size: 18px;
            text-align: center;
        }
        .qr__meta {
            display: grid;
            gap: 18px;
            align-content: center;
            padding: 28px 8px;
        }
        .qr__grid, .terminal__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(180px, 1fr));
            gap: 16px;
        }
        .qr__box, .terminal__box {
            border-radius: 24px;
            border: 1px solid rgba(214,226,244,.9);
            background: rgba(247,250,255,.95);
            padding: 18px 20px;
        }
        .qr__box small, .terminal__box small {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .qr__box strong, .terminal__box strong {
            font-size: 30px;
            line-height: 1.2;
        }
        .qr__box--wide {
            grid-column: 1 / -1;
        }
        .terminal {
            display: grid;
            place-items: center;
            text-align: center;
            align-content: center;
            gap: 22px;
            padding-block: 56px;
        }
        .terminal__icon {
            width: 148px;
            height: 148px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: 62px;
            font-weight: 900;
            color: #fff;
            box-shadow: 0 24px 54px rgba(23,49,92,.18);
        }
        .terminal__icon--success { background: linear-gradient(180deg, #31c96f, #1f9d55); }
        .terminal__icon--warning { background: linear-gradient(180deg, #ffb04e, #e67e22); }
        .terminal__hero {
            width: min(100%, 780px);
            display: grid;
            justify-items: center;
            gap: 18px;
            padding: 40px 30px;
            border-radius: 34px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.14), transparent 34%),
                linear-gradient(180deg, rgba(255,255,255,.98), rgba(244,248,255,.97));
            border: 1px solid rgba(214,226,244,.95);
            box-shadow: 0 24px 52px rgba(23,49,92,.10);
        }
        .terminal__headline {
            display: grid;
            gap: 12px;
            justify-items: center;
        }
        .terminal__grid {
            width: min(100%, 780px);
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 18px;
        }
        .terminal__box {
            min-height: 128px;
            display: grid;
            align-content: center;
            gap: 6px;
            background: linear-gradient(180deg, rgba(255,255,255,.97), rgba(243,248,255,.98));
            box-shadow: inset 0 1px 0 rgba(255,255,255,.92);
        }
        .flow {
            min-height: 100%;
            display: grid;
            align-content: center;
            justify-items: center;
            gap: 24px;
            padding: 40px 28px;
        }
        .flow__hero {
            width: min(100%, 860px);
            display: grid;
            justify-items: center;
            gap: 20px;
            padding: 30px;
            border-radius: 36px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.16), transparent 32%),
                linear-gradient(180deg, rgba(255,255,255,.98), rgba(244,248,255,.97));
            border: 1px solid rgba(214,226,244,.95);
            box-shadow: 0 24px 52px rgba(23,49,92,.10);
        }
        .flow__visual {
            width: min(100%, 460px);
            min-height: 0;
            aspect-ratio: 1 / 1;
            display: grid;
            place-items: center;
            padding: 24px;
            border-radius: 40px;
            background:
                radial-gradient(circle at top, rgba(255,203,50,.18), transparent 34%),
                linear-gradient(180deg, #ffffff, #f3f8ff);
            border: 1px solid rgba(214, 226, 244, 0.9);
            box-shadow: 0 24px 48px rgba(23,49,92,.09);
        }
        .flow__visual--compact {
            width: auto;
            min-height: auto;
            padding: 0;
        }
        .flow__visual img {
            display: block;
            width: 100%;
            max-width: 380px;
            height: auto;
            padding: 18px;
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 24px 44px rgba(23,49,92,.14);
        }
        .flow__headline {
            display: grid;
            justify-items: center;
            gap: 10px;
            text-align: center;
        }
        .flow__title {
            margin: 0;
            font-size: 54px;
            line-height: .96;
            letter-spacing: -.05em;
            font-weight: 900;
        }
        .flow__text {
            margin: 0;
            max-width: 620px;
            font-size: 21px;
            line-height: 1.5;
            color: var(--muted);
        }
        .flow__grid {
            width: min(100%, 860px);
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 16px;
        }
        .flow--waiting {
            min-height: 100%;
        }
        .flow--waiting .flow__hero {
            width: min(100%, 980px);
            min-height: calc(clamp(520px, 72vh, 980px) - 80px);
            justify-content: center;
            align-content: center;
        }
        .flow--waiting .flow__visual {
            width: min(100%, 520px);
            justify-self: center;
        }
        .flow--waiting .ads__qr-shell {
            width: min(100%, 500px);
        }
        .flow__card {
            min-height: 104px;
            display: grid;
            align-content: center;
            gap: 8px;
            padding: 18px 20px;
            text-align: center;
            border-radius: 24px;
            border: 1px solid rgba(214,226,244,.9);
            background: linear-gradient(180deg, rgba(255,255,255,.97), rgba(243,248,255,.98));
            box-shadow: inset 0 1px 0 rgba(255,255,255,.92);
        }
        .flow__card small {
            display: block;
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .flow__card strong {
            font-size: 30px;
            line-height: 1.2;
        }
        .flow__card--wide {
            grid-column: 1 / -1;
        }
        @media (orientation: landscape) and (min-width: 1100px) {
            .ads__qr-shell {
                width: min(42vw, 58vh, 520px);
            }
        }
        @media (orientation: portrait) {
            .ads, .qr, .terminal, .preview {
                padding: 24px;
            }
            .ads__qr-shell {
                width: min(74vw, 46vh, 560px);
            }
            .qr,
            .preview {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }
            .qr__meta {
                justify-items: center;
                text-align: center;
            }
            .qr__visual {
                width: min(100%, 78vw);
                max-width: 560px;
                min-height: auto;
                aspect-ratio: 1 / 1;
            }
            .qr__grid {
                width: 100%;
            }
            .terminal__grid {
                grid-template-columns: 1fr;
            }
            .terminal__hero,
            .terminal__grid {
                width: 100%;
            }
            .preview__main,
            .preview__side,
            .preview__facts,
            .preview__items {
                width: 100%;
            }
            .flow__hero,
            .flow__grid {
                width: 100%;
            }
            .flow--waiting .flow__hero {
                min-height: auto;
            }
        }
        @media (max-width: 1240px) {
            .qr, .preview, .qr__grid, .terminal__grid {
                grid-template-columns: 1fr;
            }
            .state {
                width: 100%;
            }
            .qr__title, .terminal__title, .preview__title {
                font-size: 46px;
            }
            .qr__meta {
                justify-items: center;
                text-align: center;
            }
            .preview__fact {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .preview__header,
            .preview__total {
                text-align: center;
            }
            .qr__box--wide {
                grid-column: auto;
            }
            .flow__grid {
                grid-template-columns: 1fr;
            }
            .flow__card--wide {
                grid-column: auto;
            }
        }
        @media (max-width: 720px) {
            body {
                overflow-x: hidden;
                overflow-y: auto;
            }
            .screen {
                min-height: 100dvh;
                padding: 14px;
            }
            .ads, .qr, .terminal, .preview {
                padding: 20px;
            }
            .qr__title, .terminal__title, .preview__title {
                font-size: 34px;
            }
            .qr__text, .terminal__text, .preview__text {
                font-size: 18px;
            }
            .preview__item {
                flex-direction: column;
            }
            .preview__amount,
            .preview__item strong,
            .preview__fact dd,
            .preview__total-amount {
                font-size: 28px;
            }
            .preview__total-caption {
                font-size: 20px;
            }
            .ads__qr-shell {
                width: min(86vw, 48vh, 460px);
                min-width: 0;
            }
            .qr__visual {
                width: min(100%, 86vw);
                min-width: 0;
                min-height: auto;
                aspect-ratio: 1 / 1;
            }
            .qr__grid {
                grid-template-columns: 1fr;
            }
            .qr__box--wide {
                grid-column: auto;
            }
            .terminal__grid {
                grid-template-columns: 1fr;
            }
            .terminal__hero {
                padding: 28px 20px;
                border-radius: 28px;
            }
            .terminal__icon {
                width: 124px;
                height: 124px;
                font-size: 54px;
            }
            .terminal__box {
                min-height: 110px;
            }
            .flow {
                padding: 20px 18px;
                gap: 22px;
            }
            .flow__hero {
                padding: 22px 18px;
                border-radius: 28px;
            }
            .flow__visual {
                width: min(100%, 82vw);
                min-height: auto;
                aspect-ratio: 1 / 1;
                padding: 20px;
            }
            .state {
                min-height: auto;
            }
            .flow__visual--compact {
                width: auto;
                min-height: auto;
            }
            .flow__title {
                font-size: 34px;
            }
            .flow__text {
                font-size: 18px;
            }
            .flow__grid {
                grid-template-columns: 1fr;
            }
            .flow__card--wide {
                grid-column: auto;
            }
            .flow--waiting .flow__hero {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <script
        src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.4/dist/dotlottie-wc.js"
        type="module"
    ></script>
    <div class="screen">
        <div class="content">
            <div class="state" id="monitorStateRoot"></div>
        </div>
    </div>
    <script>
        (() => {
            const monitorKey = @json($monitorKey);
            const stateRoot = document.getElementById('monitorStateRoot');
            const storageKey = 'facturacion-monitor-state:' + monitorKey;
            const broadcastName = 'facturacion-qr-monitor';
            let resetTimer = null;
            let channel = null;

            const escapeHtml = (value) => String(value || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const formatAmount = (amount) => {
                const numeric = Number(amount || 0);
                return Number.isFinite(numeric) && numeric > 0 ? 'Bs ' + numeric.toFixed(2) : 'Bs 0.00';
            };

            const normalizeImageSrc = (value) => {
                const raw = String(value || '').trim();
                if (raw === '') {
                    return '';
                }
                if (raw.startsWith('data:image') || /^https?:\/\//i.test(raw)) {
                    return raw;
                }
                return 'data:image/png;base64,' + raw;
            };

            const resolveMonitorStatusLabel = (value, fallback = 'Esperando QR') => {
                const status = String(value || '').trim().toLowerCase();
                if (status === '') {
                    return fallback;
                }

                if (['paid', 'pagado', 'approved', 'confirmed', 'completed', 'success'].includes(status)) {
                    return 'Pagado';
                }

                if (['cancelado', 'cancelled', 'rejected', 'failed', 'expired'].includes(status)) {
                    return 'No pagado';
                }

                if (['pending', 'pendiente', 'holding', 'waiting'].includes(status)) {
                    return 'Esperando pago';
                }

                return fallback;
            };

            const resolveMonitorStatusCaption = (value, fallback = 'Esperando QR...') => {
                const label = resolveMonitorStatusLabel(value, fallback.replace(/\.\.\.$/, ''));
                if (label === 'Pagado') {
                    return 'Pagado';
                }
                if (label === 'No pagado') {
                    return 'No pagado';
                }
                if (label === 'Esperando pago') {
                    return 'Esperando pago...';
                }
                if (label === 'Esperando QR') {
                    return 'Esperando QR...';
                }

                return fallback;
            };

            const baseAdsState = () => ({
                mode: 'ads',
                title: 'Pagos QR',
                message: 'Escanea o acerca tu codigo QR para realizar el pago.',
                payment_status: '',
                updated_at: new Date().toISOString(),
            });

            const sampleQrSvg = () => {
                const svg = `
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240">
                        <rect width="240" height="240" fill="#ffffff"/>
                        <rect x="16" y="16" width="56" height="56" fill="#111111"/>
                        <rect x="28" y="28" width="32" height="32" fill="#ffffff"/>
                        <rect x="36" y="36" width="16" height="16" fill="#111111"/>
                        <rect x="168" y="16" width="56" height="56" fill="#111111"/>
                        <rect x="180" y="28" width="32" height="32" fill="#ffffff"/>
                        <rect x="188" y="36" width="16" height="16" fill="#111111"/>
                        <rect x="16" y="168" width="56" height="56" fill="#111111"/>
                        <rect x="28" y="180" width="32" height="32" fill="#ffffff"/>
                        <rect x="36" y="188" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="24" width="16" height="16" fill="#111111"/>
                        <rect x="120" y="24" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="48" width="16" height="16" fill="#111111"/>
                        <rect x="136" y="48" width="16" height="16" fill="#111111"/>
                        <rect x="88" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="112" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="136" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="160" y="88" width="16" height="16" fill="#111111"/>
                        <rect x="88" y="112" width="16" height="16" fill="#111111"/>
                        <rect x="136" y="112" width="16" height="16" fill="#111111"/>
                        <rect x="184" y="112" width="16" height="16" fill="#111111"/>
                        <rect x="88" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="112" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="160" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="184" y="136" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="120" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="144" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="168" y="160" width="16" height="16" fill="#111111"/>
                        <rect x="96" y="184" width="16" height="16" fill="#111111"/>
                        <rect x="144" y="184" width="16" height="16" fill="#111111"/>
                        <rect x="192" y="184" width="16" height="16" fill="#111111"/>
                    </svg>
                `;
                return 'data:image/svg+xml;base64,' + btoa(svg);
            };

            const previewIconSvg = (name) => {
                const icons = {
                    shield: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3l7 3v6c0 4.6-3 7.9-7 9-4-1.1-7-4.4-7-9V6l7-3Z"></path>
                            <path d="m9.4 12.3 1.8 1.8 3.5-4"></path>
                        </svg>
                    `,
                    file: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M7 3.75h7.5L19.25 8.5V19A1.25 1.25 0 0 1 18 20.25H7A1.25 1.25 0 0 1 5.75 19V5A1.25 1.25 0 0 1 7 3.75Z"></path>
                            <path d="M14.5 3.75V8.5h4.75"></path>
                            <path d="M8.75 12h6.5M8.75 15.25h6.5"></path>
                        </svg>
                    `,
                    id: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3.75" y="5.25" width="16.5" height="13.5" rx="2"></rect>
                            <circle cx="8.5" cy="10.2" r="1.5"></circle>
                            <path d="M6.5 14.5c.8-1.2 3.7-1.2 4.5 0"></path>
                            <path d="M13.5 10h4M13.5 13h4"></path>
                        </svg>
                    `,
                    hash: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 3.75 7 20.25"></path>
                            <path d="M17 3.75 15 20.25"></path>
                            <path d="M4.75 9.25h14.5"></path>
                            <path d="M3.75 14.75h14.5"></path>
                        </svg>
                    `,
                    building: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4.75 20.25h14.5"></path>
                            <path d="M7 20.25V5.75A1.25 1.25 0 0 1 8.25 4.5h5.5A1.25 1.25 0 0 1 15 5.75v14.5"></path>
                            <path d="M9.25 8.25h1.5M13.25 8.25h1.5M9.25 11.75h1.5M13.25 11.75h1.5M9.25 15.25h1.5M13.25 15.25h1.5"></path>
                        </svg>
                    `,
                    mail: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3.75" y="5.5" width="16.5" height="13" rx="2"></rect>
                            <path d="m5 7 7 5 7-5"></path>
                        </svg>
                    `,
                    cart: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="9" cy="19" r="1.4"></circle>
                            <circle cx="17" cy="19" r="1.4"></circle>
                            <path d="M4.25 5.5h2l1.6 8.25h9.4l1.5-6H7.2"></path>
                        </svg>
                    `,
                    check: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m5.5 12.5 4 4 9-10"></path>
                        </svg>
                    `,
                    info: `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="8.25"></circle>
                            <path d="M12 10.25v5"></path>
                            <path d="M12 7.75h.01"></path>
                        </svg>
                    `,
                };

                return icons[name] || '';
            };

            const renderAds = (state = {}) => {
                const caption = resolveMonitorStatusCaption(state.payment_status, 'Esperando QR...');
                stateRoot.innerHTML = `
                    <div class="flow flow--waiting">
                        <div class="flow__hero">
                            <div class="ads__qr-shell">
                                <div class="ads__qr-shell-inner">
                                    <div class="ads__animation" aria-hidden="true">
                                        <dotlottie-wc
                                            src="https://lottie.host/45c84e67-4e2c-4c9f-a197-5a71ef4f9537/bsLiCO3Odf.lottie"
                                            autoplay
                                            loop
                                        ></dotlottie-wc>
                                    </div>
                                </div>
                            </div>
                            <div class="flow__headline">
                                <div class="flow__copy">
                                    <h2 class="flow__title">${escapeHtml(state.title || 'Pagos QR')}</h2>
                                    <p class="flow__text">${escapeHtml(state.message || 'Tu codigo de cobro aparecera aqui automaticamente en cuanto el cajero lo prepare.')}</p>
                                    <p class="flow__status">${escapeHtml(caption)}</p>
                                    <p class="ads__note">Mantente atento. Cuando el QR este listo, podras escanearlo al instante desde tu banca movil.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderQr = (state = {}) => {
                const imageSrc = normalizeImageSrc(state.image_data || '');
                stateRoot.innerHTML = `
                    <div class="flow">
                        <div class="flow__hero">
                            <div class="flow__visual">
                                ${imageSrc !== '' ? `<img src="${escapeHtml(imageSrc)}" alt="QR de cobro">` : '<div class="qr__empty">QR no disponible</div>'}
                            </div>
                            <div class="flow__headline">
                                <h2 class="flow__title">${escapeHtml(state.title || 'Escanea para pagar')}</h2>
                                <p class="flow__text">${escapeHtml(state.message || 'Escanea este codigo con tu app bancaria para completar el pago.')}</p>
                            </div>
                        </div>
                        <div class="flow__grid">
                            <div class="flow__card flow__card--wide">
                                <small>Monto</small>
                                <strong>${escapeHtml(formatAmount(state.amount))}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Orden</small>
                                <strong>${escapeHtml(state.internal_code || '-')}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Referencia QR</small>
                                <strong>${escapeHtml(state.transaction_id || '-')}</strong>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderPreview = (state = {}) => {
                const items = Array.isArray(state.items) ? state.items : [];
                const documentNumber = String(state.document_number || '-').trim();
                const documentComplement = String(state.document_complement || '').trim();
                const documentLabel = documentComplement !== ''
                    ? documentNumber + ' - ' + documentComplement
                    : documentNumber;
                const factRows = [
                    ['file', 'Emision', state.emission_label || '-'],
                    ['id', 'Documentacion', state.document_type_label || '-'],
                    ['hash', 'Numero de documento', documentLabel],
                    ['building', 'Razon social', state.razon_social || '-'],
                    ['mail', 'Correo para factura', state.correo_facturacion || '-'],
                ];

                stateRoot.innerHTML = `
                    <div class="preview">
                        <section class="preview__hero">
                            <div class="preview__hero-icon" aria-hidden="true">
                                ${previewIconSvg('shield')}
                            </div>
                            <div class="preview__eyebrow">Confirmacion previa</div>
                            <h2 class="preview__title">${escapeHtml(state.title || 'Confirma tus datos')}</h2>
                            <p class="preview__text">${escapeHtml(state.message || 'Verifica con el cliente la informacion antes de continuar con la emision.')}</p>
                        </section>
                        <div class="preview__main">
                            <section class="preview__panel">
                                <h3 class="preview__section-title">Informacion del cliente</h3>
                                <div class="preview__section-accent"></div>
                                <div class="preview__facts-shell">
                                    <dl class="preview__facts">
                                        ${factRows.map(([icon, label, value]) => `
                                            <div class="preview__fact">
                                                <div class="preview__fact-icon" aria-hidden="true">
                                                    ${previewIconSvg(icon)}
                                                </div>
                                                <div class="preview__fact-copy">
                                                    <dt>${escapeHtml(label)}</dt>
                                                    <dd>${escapeHtml(value)}</dd>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </dl>
                                </div>
                            </section>
                            <section class="preview__panel preview__items-panel">
                                <div class="preview__items-head">
                                    <div class="preview__items-head-icon" aria-hidden="true">
                                        ${previewIconSvg('cart')}
                                    </div>
                                    <h3>Detalle de venta</h3>
                                    <span>${escapeHtml(String(state.items_count || 0))} ${Number(state.items_count || 0) === 1 ? 'item' : 'items'}</span>
                                </div>
                                <div class="preview__items-list">
                                    ${items.length > 0
                                        ? items.map((item) => `
                                            <article class="preview__item">
                                                <div>
                                                    <strong>${escapeHtml(item && item.title ? item.title : 'Item sin nombre')}</strong>
                                                    <p>${escapeHtml([item && item.meta ? item.meta : '', item && item.recipient ? item.recipient : ''].filter(Boolean).join(' | ') || 'Sin detalle adicional')}</p>
                                                </div>
                                                <div class="preview__amount">${escapeHtml(item && item.amount ? item.amount : 'Bs 0.00')}</div>
                                            </article>
                                        `).join('')
                                        : `<article class="preview__item">
                                            <div>
                                                <strong>Sin items</strong>
                                                <p>No hay detalle para mostrar.</p>
                                            </div>
                                            <div class="preview__amount">Bs 0.00</div>
                                        </article>`}
                                </div>
                            </section>
                            <section class="preview__panel preview__summary">
                                <div class="preview__summary-card">
                                    <div class="preview__summary-badge" aria-hidden="true">
                                        ${previewIconSvg('check')}
                                    </div>
                                    <div class="preview__summary-copy">
                                        <small>Total a confirmar</small>
                                        <strong>${escapeHtml(formatAmount(state.total))}</strong>
                                        <p>Revisa los datos antes de emitir</p>
                                    </div>
                                    <div class="preview__summary-art" aria-hidden="true">
                                        <div class="preview__summary-lines">
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                `;
            };

            const renderTerminal = (state = {}) => {
                const success = ['paid', 'pagado', 'confirmed', 'success', 'approved', 'completed'].includes(String(state.terminal_status || state.payment_status || '').toLowerCase());
                const isFacturaFlow = String(state.flow_kind || '').toLowerCase() === 'factura';
                const terminalNote = isFacturaFlow
                    ? (success
                        ? 'La factura fue emitida. Puedes continuar con la siguiente atencion.'
                        : 'La emision no se completo. Revisa el resultado con el cajero.')
                    : (success
                        ? 'Pago confirmado con exito. Gracias por confiar en Correos de Bolivia.'
                        : 'Estamos revisando el resultado del cobro. Si hace falta, intenta nuevamente con el cajero.');
                stateRoot.innerHTML = `
                    <div class="flow">
                        <div class="flow__hero">
                            ${success
                                ? `<div class="terminal__animation" aria-hidden="true">
                                        <dotlottie-wc
                                            src="https://lottie.host/526540f0-307c-47db-8d4c-e53afbbdb180/C5OfkKXIVn.lottie"
                                            autoplay
                                            loop
                                        ></dotlottie-wc>
                                   </div>`
                                : `<div class="terminal__icon terminal__icon--warning">!</div>`}
                            <div class="flow__headline">
                                <div class="flow__copy">
                                    <h2 class="flow__title">${escapeHtml(state.title || 'Operacion finalizada')}</h2>
                                    <p class="flow__text">${escapeHtml(state.message || 'La pantalla volvera automaticamente al modo de espera.')}</p>
                                    <p class="terminal__note">${escapeHtml(terminalNote)}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flow__grid">
                            <div class="flow__card">
                                <small>Orden</small>
                                <strong>${escapeHtml(state.internal_code || '-')}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Referencia QR</small>
                                <strong>${escapeHtml(state.transaction_id || '-')}</strong>
                            </div>
                            <div class="flow__card">
                                <small>Monto</small>
                                <strong>${escapeHtml(formatAmount(state.amount))}</strong>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderState = (state = {}) => {
                if (resetTimer) {
                    clearTimeout(resetTimer);
                    resetTimer = null;
                }

                if (state.mode === 'qr') {
                    renderQr(state);
                } else if (state.mode === 'preview') {
                    renderPreview(state);
                } else if (state.mode === 'terminal') {
                    renderTerminal(state);
                    const resetMs = Number(state.auto_reset_ms || 8000);
                    if (resetMs > 0) {
                        resetTimer = window.setTimeout(() => {
                            const adsState = baseAdsState();
                            persistState(adsState);
                            renderAds(adsState);
                        }, resetMs);
                    }
                } else {
                    renderAds(state);
                }
            };

            const persistState = (state) => {
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(state));
                } catch (error) {
                    // Ignora errores de storage.
                }
            };

            const readPersistedState = () => {
                try {
                    const raw = window.localStorage.getItem(storageKey);
                    if (!raw) {
                        return baseAdsState();
                    }
                    const parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed !== 'object') {
                        return baseAdsState();
                    }
                    if (parsed.mode === 'terminal') {
                        const updatedAt = new Date(parsed.updated_at || Date.now()).getTime();
                        const autoResetMs = Number(parsed.auto_reset_ms || 8000);
                        if (Number.isFinite(updatedAt) && Number.isFinite(autoResetMs) && Date.now() - updatedAt >= autoResetMs) {
                            return baseAdsState();
                        }
                    }
                    return parsed;
                } catch (error) {
                    return baseAdsState();
                }
            };

            const handleIncomingState = (payload) => {
                if (!payload || typeof payload !== 'object') {
                    return;
                }
                if (String(payload.monitor_key || '') !== monitorKey) {
                    return;
                }
                const state = payload.state && typeof payload.state === 'object'
                    ? payload.state
                    : baseAdsState();
                persistState(state);
                renderState(state);
            };

            const bootstrapBroadcast = () => {
                if (!('BroadcastChannel' in window)) {
                    return;
                }
                channel = new BroadcastChannel(broadcastName);
                channel.addEventListener('message', (event) => {
                    handleIncomingState(event.data);
                });
            };

            window.addEventListener('storage', (event) => {
                if (event.key !== storageKey || !event.newValue) {
                    return;
                }
                try {
                    renderState(JSON.parse(event.newValue));
                } catch (error) {
                    renderState(baseAdsState());
                }
            });

            bootstrapBroadcast();
            renderState(readPersistedState());
        })();
    </script>
</body>
</html>
