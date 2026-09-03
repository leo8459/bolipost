@if(!empty($alertaEmpresaPendiente))
    <style>
        .company-alert-modal .modal-content { overflow: hidden; border: 0; border-radius: 20px; box-shadow: 0 24px 70px rgba(15, 35, 70, .35); }
        .company-alert-modal .company-alert-cover { display: block; width: 100%; max-height: 420px; object-fit: cover; background: #eef2f7; }
        .company-alert-modal .company-alert-body { padding: 1.5rem; }
        .company-alert-modal .company-alert-title { color: #20539a; font-weight: 800; }
        .company-alert-modal .company-alert-copy { color: #374151; line-height: 1.65; white-space: pre-line; }
        .company-alert-modal .company-alert-footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem; padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; background: #f8fafc; }
        .company-alert-modal .btn-alert-primary { border: 0; border-radius: 10px; background: #20539a; color: #fff; font-weight: 700; padding: .65rem 1.1rem; }
    </style>

    <div class="modal fade company-alert-modal" id="companyAlertModal" tabindex="-1" role="dialog"
         aria-labelledby="companyAlertTitle" aria-modal="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <img class="company-alert-cover"
                     src="{{ route('alertas-empresa.portada', $alertaEmpresaPendiente, false) }}"
                     alt="Portada de {{ $alertaEmpresaPendiente->titulo }}">
                <div class="company-alert-body">
                    <div class="text-uppercase text-muted small font-weight-bold mb-2">
                        <i class="fas fa-bell mr-1"></i>
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
