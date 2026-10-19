<nav class="ips-operation-flow" aria-label="Orden de operaciones internacionales">
    <header class="ips-operation-flow__header">
        <div>
            <span class="ips-operation-flow__eyebrow">GUÍA OPERATIVA</span>
            <h2>Orden del proceso internacional</h2>
        </div>
        <p>Abre una etapa para ver sus tareas. Aduana se realiza únicamente cuando corresponde.</p>
    </header>

    <ol class="ips-operation-stages">
        @foreach($operationGroups as $groupKey => $group)
            <li class="ips-operation-stage {{ $operationGroup === $groupKey ? 'is-current' : '' }}">
                <details name="international-operation-stages" @if($operationGroup === $groupKey) open @endif>
                    <summary>
                        <span class="ips-operation-stage__number">{{ $loop->iteration }}</span>
                        <span class="ips-operation-stage__title">{{ $group['label'] }}</span>
                        @if($groupKey === 'aduana')<span class="ips-operation-stage__optional">Solo si aplica</span>@endif
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <div class="ips-operation-stage__content">
                        <p>{{ $group['description'] }}</p>
                        <ol>
                            @foreach($group['tabs'] as $tab)
                                <li class="{{ $activeOperationTab === $tab['key'] ? 'is-active' : '' }}">
                                    <a href="{{ route($tab['route'], $tab['params'] ?? []) }}" @if($activeOperationTab === $tab['key']) aria-current="step" @endif>
                                        <span>{{ $loop->iteration }}</span>
                                        <strong>{{ $tab['label'] }}</strong>
                                        @if($activeOperationTab === $tab['key'])<small>Estás aquí</small>@endif
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </details>
            </li>
        @endforeach
    </ol>

    <p class="ips-operation-flow__note"><i class="fas fa-info-circle" aria-hidden="true"></i> Sigue los pasos que IPS habilite para cada envío. No todos los paquetes pasan por Aduana ni requieren reparto domiciliario.</p>
</nav>

<style>
.ips-operation-flow{margin:0 0 16px;padding:16px 18px;background:#fff;border:1px solid #dce6f1;border-radius:14px;box-shadow:0 4px 16px #17314d08;color:#294c72}
.ips-operation-flow__header{display:flex;justify-content:space-between;align-items:end;gap:18px;margin-bottom:12px}
.ips-operation-flow__header h2{margin:2px 0 0;font-size:1rem;font-weight:800;color:#183c69}
.ips-operation-flow__header p{margin:0;color:#6e8095;font-size:.78rem}
.ips-operation-flow__eyebrow{color:#6481a2;font-size:.62rem;letter-spacing:.12em;font-weight:800}
.ips-operation-stages{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;list-style:none;margin:0;padding:0}
.ips-operation-stage{min-width:0;border:1px solid #dce6f1;border-radius:10px;background:#f8fafd;overflow:hidden}
.ips-operation-stage.is-current{border-color:#8eb1dc;background:#f4f8ff}
.ips-operation-stage details>summary{display:flex;align-items:center;gap:8px;min-height:54px;padding:9px 10px;list-style:none;cursor:pointer}
.ips-operation-stage details>summary::-webkit-details-marker{display:none}
.ips-operation-stage__number{display:grid;place-items:center;flex:0 0 27px;height:27px;border-radius:50%;background:#e8eef6;color:#355c8a;font-size:.78rem;font-weight:800}
.ips-operation-stage.is-current .ips-operation-stage__number{background:#24538e;color:white}
.ips-operation-stage__title{flex:1;color:#294c72;font-size:.76rem;font-weight:800;line-height:1.25}
.ips-operation-stage summary>i{color:#8091a5;font-size:.68rem;transition:transform .15s ease}
.ips-operation-stage details[open] summary>i{transform:rotate(180deg)}
.ips-operation-stage__optional{padding:3px 6px;border-radius:20px;background:#fff3d5;color:#916300;font-size:.58rem;white-space:nowrap}
.ips-operation-stage__content{padding:0 10px 10px}
.ips-operation-stage__content>p{margin:0 0 8px;color:#6c7e92;font-size:.7rem;line-height:1.4}
.ips-operation-stage__content ol{display:grid;gap:5px;list-style:none;margin:0;padding:0}
.ips-operation-stage__content li a{display:flex;align-items:center;gap:7px;min-height:34px;padding:6px 8px;border:1px solid #e1e8f0;border-radius:7px;background:#fff;color:#365778;text-decoration:none;font-size:.68rem;line-height:1.25}
.ips-operation-stage__content li a:hover{border-color:#8eb1dc;background:#f7faff}
.ips-operation-stage__content li.is-active a{border-color:#8eb1dc;background:#eaf2ff;color:#204c82}
.ips-operation-stage__content li a>span{display:grid;place-items:center;flex:0 0 19px;height:19px;border-radius:50%;background:#edf1f6;color:#4a6888;font-size:.62rem;font-weight:800}
.ips-operation-stage__content li.is-active a>span{background:#24538e;color:#fff}
.ips-operation-stage__content li a strong{flex:1;font-weight:700}
.ips-operation-stage__content li a small{color:#24538e;font-size:.58rem;white-space:nowrap}
.ips-operation-flow__note{display:flex;gap:7px;align-items:flex-start;margin:10px 1px 0;color:#718299;font-size:.7rem}
.ips-operation-flow__note i{color:#3f78b8;margin-top:2px}
@media(max-width:1050px){.ips-operation-stages{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.ips-operation-flow__header{display:block}.ips-operation-flow__header p{margin-top:5px}.ips-operation-stages{grid-template-columns:1fr}}
</style>
