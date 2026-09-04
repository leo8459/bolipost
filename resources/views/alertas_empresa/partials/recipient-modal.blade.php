@if(!empty($alertaEmpresaPendiente))
    <style>
        .company-alert-modal .modal-dialog { width: calc(100% - 2rem); max-width: 1120px; margin: 1rem auto; }
        .company-alert-modal .modal-content { display: grid; grid-template-columns: minmax(330px, 40%) minmax(0, 1fr); grid-template-rows: minmax(0, 1fr) auto; max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); overflow: hidden; border: 0; border-radius: 22px; box-shadow: 0 28px 76px rgba(11, 31, 63, .38); }
        .company-alert-modal .company-alert-aside { grid-row: 1 / span 2; display: flex; flex-direction: column; gap: 1.25rem; padding: 1.4rem; color: #fff; background: radial-gradient(circle at 12% 10%, rgba(255, 204, 58, .22), transparent 35%), linear-gradient(160deg, #123c70 0%, #20539a 56%, #102c55 100%); }
        .company-alert-modal .company-alert-brand { display: flex; align-items: center; gap: .7rem; font-size: .72rem; font-weight: 800; letter-spacing: .1em; }
        .company-alert-modal .company-alert-brand i { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 10px; color: #143d72; background: #ffd15a; font-size: 1rem; }
        .company-alert-modal .company-alert-brand small { display: block; margin-top: .15rem; color: rgba(255, 255, 255, .66); font-size: .62rem; font-weight: 600; letter-spacing: .04em; }
        .company-alert-modal .company-alert-cover { display: block; width: 100%; min-height: 180px; flex: 1 1 auto; object-fit: cover; border: 1px solid rgba(255, 255, 255, .28); border-radius: 16px; background: linear-gradient(135deg, #eef3f9, #cad8e9); box-shadow: 0 14px 26px rgba(4, 23, 49, .26); }
        .company-alert-modal .company-alert-aside-note { margin: 0; color: rgba(255, 255, 255, .78); font-size: .78rem; line-height: 1.5; }
        .company-alert-modal .company-alert-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 2rem 2.2rem; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .company-alert-modal .company-alert-kicker { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: .85rem; color: #547095; font-size: .72rem; font-weight: 800; letter-spacing: .1em; }
        .company-alert-modal .company-alert-kicker i { color: #d29014; }
        .company-alert-modal .company-alert-title { max-width: 720px; margin-bottom: 1.25rem; color: #173f76; font-weight: 800; line-height: 1.08; letter-spacing: -.015em; }
        .company-alert-modal .company-alert-copy { max-width: 760px; color: #374151; font-size: 1.02rem; line-height: 1.72; white-space: pre-line; }
        .company-alert-modal .company-alert-footer { display: flex; grid-column: 2; align-items: center; flex-wrap: wrap; justify-content: space-between; gap: .75rem; padding: 1rem 2.2rem; border-top: 1px solid #e4ebf4; background: #f8fafc; }
        .company-alert-modal .company-alert-footer .btn { border-radius: 9px; font-weight: 700; }
        .company-alert-modal .btn-alert-primary { border: 0; border-radius: 10px; background: linear-gradient(135deg, #2864b4, #163f78); color: #fff; font-weight: 700; padding: .72rem 1.2rem; box-shadow: 0 8px 16px rgba(25, 75, 139, .22); }
        @media (max-width: 575.98px) {
            .company-alert-modal .modal-dialog { width: calc(100% - 1rem); margin: .5rem auto; }
            .company-alert-modal .modal-content { display: flex; flex-direction: column; max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); border-radius: 14px; }
            .company-alert-modal .company-alert-aside { display: block; flex: 0 0 auto; padding: .75rem; }
            .company-alert-modal .company-alert-brand, .company-alert-modal .company-alert-aside-note { display: none; }
            .company-alert-modal .company-alert-cover { width: 100%; min-height: 0; height: auto; max-height: 145px; }
            .company-alert-modal .company-alert-body { padding: 1.1rem; }
            .company-alert-modal .company-alert-title { font-size: 1.35rem; }
            .company-alert-modal .company-alert-footer { flex: 0 0 auto; padding: .85rem 1.1rem; }
            .company-alert-modal .company-alert-footer > div, .company-alert-modal .company-alert-footer form { width: 100%; }
            .company-alert-modal .company-alert-footer .btn, .company-alert-modal .btn-alert-primary { width: 100%; }
        }
    </style>

    <div class="modal fade company-alert-modal" id="companyAlertModal" tabindex="-1" role="dialog"
         aria-labelledby="companyAlertTitle" aria-modal="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <aside class="company-alert-aside" aria-label="Información del comunicado">
                    <div class="company-alert-brand">
                        <i class="fas fa-bullhorn"></i>
                        <span>COMUNICADOS<small>Correos de Bolivia</small></span>
                    </div>
                    <img class="company-alert-cover"
                         src="{{ route('alertas-empresa.portada', $alertaEmpresaPendiente, false) }}"
                         alt="Portada de {{ $alertaEmpresaPendiente->titulo }}">
                    <p class="company-alert-aside-note">Información institucional para mantener a nuestro equipo informado.</p>
                </aside>
                <div class="company-alert-body">
                    <div class="company-alert-kicker">
                        <i class="fas fa-bell"></i>
                        {{ auth()->user()?->empresa_id ? 'Comunicado para tu empresa' : 'Comunicado para ti' }}
                    </div>
                    <h3 class="company-alert-title mb-3" id="companyAlertTitle">{{ $alertaEmpresaPendiente->titulo }}</h3>
                    @if(filled($alertaEmpresaPendiente->mensaje))
                        <div class="company-alert-copy">{{ $alertaEmpresaPendiente->mensaje }}</div>
                    @endif
                </div>
                <div class="company-alert-footer">
                    <div>
                        @if(filled($alertaEmpresaPendiente->pdf_path))
                            <a class="btn btn-outline-danger" href="{{ route('alertas-empresa.pdf', $alertaEmpresaPendiente, false) }}" target="_blank" rel="noopener">
                                <i class="fas fa-file-pdf mr-1"></i> Ver PDF adjunto
                            </a>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('alertas-empresa.read', $alertaEmpresaPendiente, false) }}">
                        @csrf
                        <button class="btn-alert-primary" type="submit">
                            <i class="fas fa-check mr-1"></i> Entendido, marcar como visto
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                $('#companyAlertModal').modal({ backdrop: 'static', keyboard: false });
            });
        </script>
    @endpush
@endif
