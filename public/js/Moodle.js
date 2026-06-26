/**
 * Moodle Integration Module - JavaScript V3.0 (Optimizado)
 * Features: Health Check, Circuit Breaker, Real-time Log, Sync Control, Performance Metrics
 */

// =====================================
// STATE MANAGEMENT
// =====================================
let isSyncing = false;
let eventSource = null;
let healthCheckInterval = null;
let syncStartTime = null;

// Metrics tracking v3.5
let lastProcessedCount = 0;
let lastPulseTime = 0;
let instantRate = 0;
let rateHistory = []; // Para suavizado (moving average)
let processedCount = 0; // Contador global de registros procesados
let lastSyncMessage = ''; // Para rastrear cambios en el mensaje y agregarlos al log

// =====================================
// INITIALIZATION
// =====================================
document.addEventListener('DOMContentLoaded', function () {
    console.log('Moodle.js loaded, BASE_URL:', window.BASE_URL); // DEBUG

    // Initial checks
    checkHealth();

    // Timer para actualizar estado inmediatamente y luego periódicamente
    setTimeout(function () {
        console.log('Calling checkGlobalStatus...'); // DEBUG
        checkGlobalStatus();
    }, 500);

    // Polling cada 5 segundos (reducido para menos ruido en consola)
    setInterval(checkGlobalStatus, 5000);

    // Periodic health check every 30 seconds
    healthCheckInterval = setInterval(checkHealth, 30000);

    // Job history refresh every 20 seconds
    setInterval(loadJobHistory, 20000);

    // =====================================
    // EVENT LISTENERS (NEW)
    // =====================================

    // Health check refresh
    const btnHealth = document.getElementById('btn-health-refresh');
    if (btnHealth) btnHealth.addEventListener('click', checkHealth);

    // Circuit breaker reset
    const btnCircuit = document.getElementById('btn-circuit-reset');
    if (btnCircuit) btnCircuit.addEventListener('click', resetCircuit);

    // Master Sync controls
    const btnSyncStart = document.getElementById('btn-sync-start');
    if (btnSyncStart) {
        btnSyncStart.dataset.originalHtml = btnSyncStart.innerHTML;
        btnSyncStart.addEventListener('click', startGlobalSync);
    }

    const btnSyncStop = document.getElementById('btn-sync-stop');
    if (btnSyncStop) {
        btnSyncStop.dataset.originalHtml = btnSyncStop.innerHTML;
        btnSyncStop.addEventListener('click', stopGlobalSync);
        btnSyncStop.style.display = 'none';
    }

    const btnResetTotal = document.getElementById('btn-reset-processes');
    if (btnResetTotal) btnResetTotal.addEventListener('click', resetAllProcesses);

    const btnStartWorker = document.getElementById('btn-start-worker');
    const btnStopWorker = document.getElementById('btn-stop-worker');
    if (btnStartWorker) {
        btnStartWorker.dataset.originalHtml = btnStartWorker.innerHTML;
        btnStartWorker.addEventListener('click', startWorkerProcess);
    }
    if (btnStopWorker) {
        btnStopWorker.dataset.originalHtml = btnStopWorker.innerHTML;
        btnStopWorker.addEventListener('click', stopWorkerProcess);
        btnStopWorker.style.display = 'none';
    }

    refreshWorkerStatus();
    setInterval(refreshWorkerStatus, 10000);

    // Log controls
    const btnExportLog = document.getElementById('btn-export-log');
    if (btnExportLog) btnExportLog.addEventListener('click', exportLog);

    const btnClearLog = document.getElementById('btn-clear-log');
    if (btnClearLog) btnClearLog.addEventListener('click', clearLog);

    const btnRefreshJobs = document.getElementById('btn-refresh-jobs');
    if (btnRefreshJobs) btnRefreshJobs.addEventListener('click', loadJobHistory);

    // Single entity sync buttons
    document.querySelectorAll('.btn-sync-entity').forEach(btn => {
        btn.addEventListener('click', function () {
            const entity = this.dataset.entity;
            if (entity) syncEntity(entity);
        });
    });

    logMessage('Sistema optimizado v3.0 inicializado correctamente', 'success');
});

// =====================================
// UTILITIES
// =====================================
function getCsrfToken() {
    return document.getElementById('csrf-container').dataset.token;
}

function getTimestamp() {
    return new Date().toLocaleTimeString('es-PY', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function logMessage(msg, type = 'info') {
    const logContainer = document.getElementById('sync-log');
    if (!logContainer) return;

    const entry = document.createElement('div');
    entry.className = `log-entry ${type}`;

    const timeSpan = document.createElement('span');
    timeSpan.className = 'log-time';
    timeSpan.textContent = getTimestamp();
    const msgSpan = document.createElement('span');
    msgSpan.className = 'log-msg';
    msgSpan.textContent = msg;
    entry.appendChild(timeSpan);
    entry.appendChild(msgSpan);

    logContainer.appendChild(entry);
    logContainer.scrollTop = logContainer.scrollHeight;

    // Limit to 100 entries
    while (logContainer.children.length > 100) {
        logContainer.removeChild(logContainer.firstChild);
    }
}

function clearLog() {
    const logContainer = document.getElementById('sync-log');
    if (logContainer) {
        logContainer.innerHTML = '';
        logMessage('Log limpiado', 'info');
    }
}

function exportLog() {
    const logContainer = document.getElementById('sync-log');
    if (!logContainer) return;

    const entries = logContainer.querySelectorAll('.log-entry');
    let content = 'EDUMA - Log de Sincronización Moodle\n';
    content += '='.repeat(50) + '\n';
    content += `Exportado: ${new Date().toLocaleString('es-PY')}\n\n`;

    entries.forEach(entry => {
        const time = entry.querySelector('.log-time')?.textContent || '';
        const msg = entry.querySelector('.log-msg')?.textContent || '';
        const type = entry.classList.contains('error') ? '[ERROR]' :
            entry.classList.contains('success') ? '[OK]' :
                entry.classList.contains('warning') ? '[WARN]' : '[INFO]';
        content += `${time} ${type} ${msg}\n`;
    });

    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `sync_log_${new Date().toISOString().slice(0, 10)}.txt`;
    a.click();
    URL.revokeObjectURL(url);

    logMessage('Log exportado correctamente', 'success');
}

function formatDuration(seconds) {
    if (seconds < 60) return `${Math.floor(seconds)}s`;
    if (seconds < 3600) {
        const min = Math.floor(seconds / 60);
        const sec = Math.floor(seconds % 60);
        return `${min}m ${sec}s`;
    }
    const hr = Math.floor(seconds / 3600);
    const min = Math.floor((seconds % 3600) / 60);
    return `${hr}h ${min}m`;
}

function isApiSuccess(data) {
    return data && (data.success === true || data.status === 'success');
}

function getApiMessage(data, fallback = '') {
    if (!data) return fallback;
    return data.message || (data.data && data.data.message) || fallback;
}

function getBaseUrl() {
    return window.BASE_URL || document.body.dataset.baseUrl || '/';
}

function fetchJson(url, options = {}) {
    const baseHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
    };

    const mergedOptions = {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...baseHeaders,
            ...(options.headers || {})
        }
    };

    return fetch(url, mergedOptions);
}

// =====================================
// HEALTH CHECK
// =====================================
function checkHealth() {
    const indicator = document.getElementById('health-indicator');
    const dot = indicator?.querySelector('.health-dot');

    // Set loading state
    if (indicator) indicator.className = 'health-indicator loading';
    if (dot) dot.className = 'health-dot loading';

    fetchJson(getBaseUrl() + 'moodle/health', {
        method: 'GET'
    })
        .then(res => {
            const contentType = res.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return res.json().then(data => ({ status: res.status, data }));
            } else {
                return res.text().then(text => {
                    if (text.includes('Iniciar Sesión') || text.includes('login')) {
                        throw new Error('Sesión expirada - recargue la página');
                    }
                    throw new Error(`Respuesta no es JSON`);
                });
            }
        })
        .then(({ status, data }) => {
            if (status >= 400) {
                throw new Error(data.error || data.message || `HTTP ${status}`);
            }

            if (isApiSuccess(data) && data.data) {
                const d = data.data;

                // Update UI
                document.getElementById('health-sitename').textContent = d.sitename || 'Conectado';
                document.getElementById('health-version').textContent = d.version || '-';
                document.getElementById('health-username').textContent = d.username || '-';
                document.getElementById('health-functions').textContent = d.functions_count ? `${d.functions_count} disponibles` : '-';

                // Success state
                if (indicator) indicator.className = 'health-indicator';
                if (dot) dot.className = 'health-dot';

                // Update circuit breaker
                if (d.circuit_breaker) {
                    updateCircuitBreakerUI(d.circuit_breaker);
                }

            } else if (!isApiSuccess(data)) {
                throw new Error(data.error || 'Error de conexión con Moodle');
            }
        })
        .catch(err => {
            if (indicator) indicator.className = 'health-indicator error';
            if (dot) dot.className = 'health-dot error';
            document.getElementById('health-sitename').textContent = 'Error';
            logMessage(`Health check fallido: ${err.message}`, 'error');
        });
}

// =====================================
// CIRCUIT BREAKER
// =====================================
function updateCircuitBreakerUI(cb) {
    const indicator = document.getElementById('circuit-indicator');
    const statusEl = document.getElementById('circuit-status');
    const failuresEl = document.getElementById('circuit-failures');
    const resetRowEl = document.getElementById('circuit-reset-row');
    const resetTimeEl = document.getElementById('circuit-reset-time');
    const resetBtn = document.getElementById('btn-circuit-reset');

    if (!cb) return;

    const isOpen = cb.is_open;
    const failures = cb.consecutive_failures || 0;
    const threshold = cb.threshold || 10;

    if (indicator) {
        indicator.className = isOpen ? 'circuit-indicator open' :
            (failures > threshold / 2 ? 'circuit-indicator warning' : 'circuit-indicator');
    }

    if (statusEl) {
        if (isOpen) {
            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">ABIERTO</span>';
        } else if (failures > threshold / 2) {
            statusEl.innerHTML = '<span class="badge bg-soft-warning text-warning">ADVERTENCIA</span>';
        } else {
            statusEl.innerHTML = '<span class="badge bg-soft-success text-success">CERRADO</span>';
        }
    }

    if (failuresEl) {
        failuresEl.textContent = `${failures} / ${threshold}`;
    }

    if (isOpen) {
        if (resetRowEl) resetRowEl.classList.remove('d-none');
        if (resetBtn) resetBtn.classList.remove('d-none');

        if (cb.open_since && cb.reset_time) {
            const elapsed = Math.floor(Date.now() / 1000) - cb.open_since;
            const remaining = Math.max(0, cb.reset_time - elapsed);
            if (resetTimeEl) resetTimeEl.textContent = `${remaining}s`;
        }
    } else {
        if (resetRowEl) resetRowEl.classList.add('d-none');
        if (resetBtn) resetBtn.classList.add('d-none');
    }
}

function resetCircuit() {
    const csrfToken = getCsrfToken();

    Swal.fire({
        title: 'Reset Circuit Breaker',
        text: '¿Desea resetear el circuit breaker manualmente?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, resetear',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetchJson(getBaseUrl() + 'moodle/reset-circuit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}`
            })
                .then(res => res.json())
                .then(data => {
                    if (isApiSuccess(data)) {
                        Swal.fire('Reseteado', 'Circuit breaker reseteado correctamente', 'success');
                        logMessage('Circuit breaker reseteado manualmente', 'warning');
                        checkHealth();
                    } else {
                        throw new Error(getApiMessage(data, 'Error al resetear'));
                    }
                })
                .catch(err => {
                    Swal.fire('Error', err.message, 'error');
                });
        }
    });
}

function resetAllProcesses() {
    const csrfToken = getCsrfToken();

    Swal.fire({
        title: 'Reset Total del Sistema',
        text: '¿Desea forzar el reseteo de todos los procesos de sincronización y jobs? Use esto solo si el sistema está atascado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, ¡Resetear Todo!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetchJson(getBaseUrl() + 'moodle/reset-processes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}`
            })
                .then(res => {
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}`);
                    }
                    return res.json();
                })
                .then(data => {
                    // Soportar ambos formatos: {success: true, message} y {status: 'success', data: {message}}
                    const isSuccess = data.success === true || data.status === 'success';
                    const message = data.message || (data.data && data.data.message) || 'Procesos reseteados correctamente';

                    if (isSuccess) {
                        Swal.fire('Reseteado', message, 'success');
                        logMessage('Sistema reseteado manualmente', 'warning');
                        isSyncing = false;
                        syncStartTime = null;
                        processedCount = 0;
                        updateUIState(false);
                        checkHealth();
                    } else {
                        const errorMsg = data.message || (data.data && data.data.message) || 'Error desconocido';
                        Swal.fire('Error', errorMsg, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', err.message, 'error');
                });
        }
    });
}


// =====================================
// SYNC CONTROL - V3.0 OPTIMIZADO
// =====================================
function getSyncTypeLabel(type) {
    const typeLabels = {
        'all': 'completa',
        'delta': 'incremental (últimas 24h)',
        'categories': 'de categorías',
        'courses': 'de cursos',
        'users': 'de usuarios',
        'unlocked_users': 'de usuarios desbloqueados',
        'enrollments': 'de matrículas',
        'enrollments_2026': 'de matrículas 2026',
        'cohorts': 'de cohortes',
        'grades': 'de calificaciones'
    };

    return typeLabels[type] || type;
}

function startGlobalSync() {
    const csrfToken = getCsrfToken();
    const syncType = document.getElementById('sync-type')?.value || 'all';
    const forceSync = document.getElementById('sync-force')?.checked || false;
    const regenPasswords = document.getElementById('sync-regenerate-passwords')?.checked || false;

    const label = getSyncTypeLabel(syncType);

    Swal.fire({
        title: 'Iniciar Sincronización',
        html: `
            <p>Se iniciará la sincronización <strong>${label}</strong></p>
            ${forceSync ? '<p class="text-warning"><i class="bi bi-exclamation-triangle"></i> Modo forzado: se re-procesarán todos los registros</p>' : ''}
            ${regenPasswords ? '<p class="text-info"><i class="bi bi-key-fill"></i> Se regenerarán contraseñas para usuarios existentes</p>' : ''}
            <p class="text-muted small">El sistema utilizará procesamiento paralelo y operaciones masivas para optimizar el rendimiento.</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-rocket-takeoff me-1"></i>Iniciar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#6366f1'
    }).then((result) => {
        if (result.isConfirmed) {
            syncStartTime = Date.now();
            processedCount = 0;
            updateUIState(true);
            logMessage(`Iniciando sincronización ${label}...`, 'info');

            fetchJson(getBaseUrl() + 'moodle/sync/asyncStart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&type=${syncType}&force=${forceSync ? 1 : 0}&regenerate_passwords=${regenPasswords ? 1 : 0}&optimized=1`
            })
                .then(res => res.json())
                .then(data => {
                    const isSuccess = data.success === true || data.status === 'success';
                    const message = data.message || (data.data && data.data.message) || 'El proceso ha comenzado';

                    if (isSuccess) {
                        Swal.fire({
                            title: 'Sincronización Iniciada',
                            text: message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        logMessage(`Sincronización ${label} iniciada correctamente`, 'success');
                        startPolling();
                    } else {
                        const errorMsg = data.message || 'Error desconocido';
                        Swal.fire('Error', errorMsg, 'error');
                        logMessage(`Error al iniciar: ${errorMsg}`, 'error');
                        updateUIState(false);
                        setSyncActionState('idle');
                    }
                })
                .catch(err => {
                    Swal.fire('Error de Red', err.message, 'error');
                    logMessage(`Error de red: ${err.message}`, 'error');
                    updateUIState(false);
                    setSyncActionState('idle');
                });
        }
    });
}

function stopGlobalSync() {
    const csrfToken = getCsrfToken();

    Swal.fire({
        title: '¿Detener Sincronización?',
        text: 'El proceso se detendrá de forma segura al finalizar el lote actual.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: '<i class="bi bi-stop-fill me-1"></i>Detener',
        cancelButtonText: 'Continuar'
    }).then((result) => {
        if (result.isConfirmed) {
            logMessage('Solicitando detención del proceso...', 'warning');
            setSyncActionState('stopping');

            fetchJson(getBaseUrl() + 'moodle/sync/asyncStop', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}`
            })
                .then(res => res.json())
                .then(data => {
                    Swal.fire({
                        title: 'Deteniendo...',
                        text: data.message,
                        icon: 'info',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    logMessage('Señal de detención enviada', 'warning');
                })
                .catch(err => {
                    Swal.fire('Error', 'No se pudo enviar la señal de detención: ' + err.message, 'error');
                    logMessage(`Error al detener: ${err.message}`, 'error');
                    setSyncActionState('running');
                });
        }
    });
}

function setSyncActionState(state) {
    const btnStart = document.getElementById('btn-sync-start');
    const btnStop = document.getElementById('btn-sync-stop');
    if (!btnStart || !btnStop) return;

    const originalStartHtml = btnStart.dataset.originalHtml || '<i class="bi bi-rocket-takeoff me-1"></i>Iniciar';
    const originalStopHtml = btnStop.dataset.originalHtml || '<i class="bi bi-stop-fill me-1"></i>Detener';

    switch (state) {
        case 'starting':
            btnStart.disabled = true;
            btnStart.innerHTML = '<span class="spinner-border spinner-border-sm text-white me-2" role="status" aria-hidden="true"></span>Iniciando...';
            btnStop.disabled = true;
            btnStop.style.display = 'none';
            break;
        case 'stopping':
            btnStart.disabled = true;
            btnStop.disabled = true;
            btnStop.style.display = '';
            btnStop.innerHTML = '<span class="spinner-border spinner-border-sm text-white me-2" role="status" aria-hidden="true"></span>Deteniendo...';
            break;
        case 'running':
            btnStart.disabled = true;
            btnStop.disabled = false;
            btnStop.style.display = '';
            btnStart.innerHTML = originalStartHtml;
            btnStop.innerHTML = originalStopHtml;
            break;
        default:
            btnStart.disabled = false;
            btnStart.innerHTML = originalStartHtml;
            btnStop.disabled = true;
            btnStop.style.display = 'none';
            btnStop.innerHTML = originalStopHtml;
            break;
    }
}

function syncEntity(entity) {
    const csrfToken = getCsrfToken();
    const forceSync = document.getElementById('sync-force')?.checked || false;

    logMessage(`Iniciando sincronización de ${entity}...`, 'info');

    fetchJson(getBaseUrl() + `moodle/sync/${entity}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `csrf_token=${encodeURIComponent(csrfToken)}&force=${forceSync ? 1 : 0}`
    })
        .then(res => res.json())
        .then(data => {
            if (isApiSuccess(data)) {
                const message = getApiMessage(data, 'Sincronización iniciada');
                Swal.fire({
                    title: 'Sincronización Iniciada',
                    html: `<p>${message}</p><small class="text-muted">Job ID: ${data.job_id || 'N/A'}</small>`,
                    icon: 'success',
                    timer: 2500,
                    showConfirmButton: false
                });
                logMessage(`${entity}: ${message}`, 'success');
                startPolling();
            } else {
                const errorMsg = getApiMessage(data, 'Error desconocido');
                Swal.fire('Error', errorMsg, 'error');
                logMessage(`${entity}: Error - ${errorMsg}`, 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', err.message, 'error');
            logMessage(`${entity}: Error de red - ${err.message}`, 'error');
        });
}

function startWorkerProcess() {
    const csrfToken = getCsrfToken();
    logMessage('Solicitando inicio de worker de cola...', 'info');

    setWorkerButtonState('starting');

    fetchJson(getBaseUrl() + 'moodle/sync/start-worker', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `csrf_token=${encodeURIComponent(csrfToken)}`
    })
        .then(async res => {
            const text = await res.text();
            if (!res.ok) {
                return { success: false, message: text || `HTTP ${res.status}` };
            }
            try {
                return JSON.parse(text);
            } catch (parseError) {
                return { success: false, message: text || parseError.message };
            }
        })
        .then(data => {
            if (isApiSuccess(data)) {
                const message = getApiMessage(data, 'Worker iniciado correctamente');
                Swal.fire({
                    title: 'Worker Iniciado',
                    text: message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                logMessage('Worker iniciado correctamente', 'success');
                updateWorkerStatusUI(true, message);
                if (data.already_running) {
                    logMessage('El worker ya estaba en ejecución.', 'info');
                }
                checkGlobalStatus();
            } else {
                const errorMsg = getApiMessage(data, 'No se pudo iniciar el worker');
                Swal.fire('Error', errorMsg, 'error');
                logMessage(`Error al iniciar worker: ${errorMsg}`, 'error');
                updateWorkerStatusUI(false, errorMsg);
            }
        })
        .catch(err => {
            Swal.fire('Error de Red', err.message, 'error');
            logMessage(`Error al iniciar worker: ${err.message}`, 'error');
            updateWorkerStatusUI(false, err.message);
        })
        .finally(() => {
            if (document.getElementById('worker-status-badge')?.dataset.state !== 'running') {
                setWorkerButtonState('idle');
            }
        });
}

function stopWorkerProcess() {
    Swal.fire({
        title: 'Detener Worker',
        text: '¿Estás seguro de que deseas detener el worker? Esto finalizará el procesamiento de la cola.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, detener',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then(result => {
        if (!result.isConfirmed) {
            return;
        }

        const csrfToken = getCsrfToken();
        logMessage('Solicitando detención del worker...', 'warning');

        setWorkerButtonState('stopping');

        fetchJson(getBaseUrl() + 'moodle/sync/stop-worker', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}`
        })
        .then(async res => {
            const text = await res.text();
            if (!res.ok) {
                return { success: false, message: text || `HTTP ${res.status}` };
            }
            try {
                return JSON.parse(text);
            } catch (parseError) {
                return { success: false, message: text || parseError.message };
            }
        })
        .then(data => {
            if (isApiSuccess(data)) {
                const message = getApiMessage(data, 'Worker detenido correctamente');
                Swal.fire({
                    title: 'Worker Detenido',
                    text: message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                logMessage('Worker detenido correctamente', 'success');
                updateWorkerStatusUI(false, message);
            } else {
                const errorMsg = getApiMessage(data, 'No se pudo detener el worker');
                Swal.fire('Error', errorMsg, 'error');
                logMessage(`Error al detener worker: ${errorMsg}`, 'error');
                updateWorkerStatusUI(true, errorMsg);
            }
        })
        .catch(err => {
            Swal.fire('Error de Red', err.message, 'error');
            logMessage(`Error al detener worker: ${err.message}`, 'error');
            updateWorkerStatusUI(true, err.message);
        })
        .finally(() => {
            refreshWorkerStatus();
        });
    });
}

function refreshWorkerStatus() {
    const baseUrl = getBaseUrl();
    const badge = document.getElementById('worker-status-badge');
    if (!badge) return;

    fetchJson(baseUrl + 'moodle/sync/worker-status', {
        method: 'GET'
    })
        .then(async res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return res.json();
            }
            const text = await res.text();
            if (text.includes('Iniciar Sesión') || text.includes('login')) {
                throw new Error('Sesión expirada - recargue la página');
            }
            throw new Error(`Respuesta no es JSON: ${text.slice(0, 100)}`);
        })
        .then(data => {
            const isSuccess = isApiSuccess(data);
            if (!isSuccess || !data.data) {
                throw new Error(getApiMessage(data, 'No se pudo leer el estado del worker'));
            }
            updateWorkerStatusUI(data.data.running, data.data.message);
        })
        .catch(err => {
            console.error('Error verificando estado del worker:', err);
            updateWorkerStatusUI(false, 'Error al verificar estado');
            logMessage(`Estado worker no disponible: ${err.message}`, 'warning');
        });
}

function setWorkerButtonState(state) {
    const btnStart = document.getElementById('btn-start-worker');
    const btnStop = document.getElementById('btn-stop-worker');
    if (!btnStart) return;
    const originalHtml = btnStart.dataset.originalHtml || '<i class="bi bi-play-fill me-2"></i>Iniciar Worker';
    const originalStopHtml = btnStop?.dataset.originalHtml || '<i class="bi bi-stop-fill me-2"></i>Detener Worker';

    switch (state) {
        case 'starting':
            btnStart.disabled = true;
            btnStart.innerHTML = '<span class="spinner-border spinner-border-sm text-white me-2" role="status" aria-hidden="true"></span>Iniciando...';
            if (btnStop) {
                btnStop.disabled = true;
                btnStop.style.display = 'none';
            }
            break;
        case 'running':
            btnStart.disabled = true;
            btnStart.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Worker activo';
            if (btnStop) {
                btnStop.disabled = false;
                btnStop.style.display = '';
                btnStop.innerHTML = originalStopHtml;
            }
            break;
        case 'stopping':
            btnStart.disabled = true;
            btnStart.innerHTML = originalHtml;
            if (btnStop) {
                btnStop.disabled = true;
                btnStop.style.display = '';
                btnStop.innerHTML = '<span class="spinner-border spinner-border-sm text-white me-2" role="status" aria-hidden="true"></span>Deteniendo...';
            }
            break;
        default:
            btnStart.disabled = false;
            btnStart.innerHTML = originalHtml;
            if (btnStop) {
                btnStop.disabled = true;
                btnStop.style.display = 'none';
                btnStop.innerHTML = originalStopHtml;
            }
            break;
    }
}

function updateWorkerStatusUI(running, message = '') {
    const badge = document.getElementById('worker-status-badge');
    if (!badge) return;
    const statusText = running ? 'activo' : 'detenido';
    const extra = message ? ` — ${message}` : '';

    badge.dataset.state = running ? 'running' : 'idle';
    badge.className = running ? 'badge bg-success text-white' : 'badge bg-soft-secondary text-secondary';
    badge.textContent = `Worker: ${statusText}${extra}`;

    setWorkerButtonState(running ? 'running' : 'idle');
}

// =====================================
// POLLING & STATUS
// =====================================
function checkGlobalStatus() {
    // Asegurar que BASE_URL exista
    const baseUrl = getBaseUrl();

    fetchJson(baseUrl + 'moodle/sync/getAsyncStatus', {
        method: 'GET'
    })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Respuesta no es JSON');
            }
            return res.json();
        })
        .then(data => {
            console.log('Status response:', data); // DEBUG

            // Soportar ambos formatos: {success: true, data: {...}} y {status: 'success', data: {...}}
            const isSuccessful = data.success === true || data.status === 'success';

            if (isSuccessful && data.data) {
                const status = data.data;
                const isRunning = status.status === 'running' || status.status === 'stopping';

                updateUIState(isRunning);
                updateProgress(status);
                updateStatusBadge(status.status);
                updatePerformanceMetrics(status);

                if (isRunning && !eventSource) {
                    startPolling();
                } else if (!isRunning) {
                    updateUIState(false);
                    if (eventSource) {
                        stopPolling();
                    }
                }
            }
        })
        .catch(err => {
            console.error('Status check error:', err);
            // Si hay error de red persistente, asumir detenido para liberar UI
            if (isSyncing) {
                // No forzar inmediatamente, solo loguear
            }
        });
}

function startPolling() {
    if (eventSource) return;

    const url = getBaseUrl() + 'moodle/sync/streamProgress';
    logMessage('Conectando vía Real-Time Stream...', 'info');

    eventSource = new EventSource(url);

    eventSource.onmessage = function (event) {
        try {
            const status = JSON.parse(event.data);
            const isRunning = status.status === 'running' || status.status === 'stopping';

            updateUIState(isRunning);
            updateProgress(status);
            updateStatusBadge(status.status);
            updatePerformanceMetrics(status);

            if (!isRunning) {
                stopPolling();
            }
        } catch (e) {
            console.error('Error parsing SSE data:', e);
        }
    };

    eventSource.onerror = function (err) {
        console.error('SSE Error:', err);
        if (eventSource.readyState === EventSource.CLOSED) {
            stopPolling();
            logMessage('Conexión SSE perdida. Reintentando en 10s...', 'warning');
            setTimeout(() => {
                if (isSyncing) startPolling();
            }, 10000);
        }
    };
}

function stopPolling() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
        logMessage('Stream de progreso finalizado', 'info');
    }
}

function updateUIState(running) {
    const btnStart = document.getElementById('btn-sync-start');
    const btnStop = document.getElementById('btn-sync-stop');
    const configLayer = document.getElementById('sync-config-layer');
    const monitorLayer = document.getElementById('sync-monitor-layer');
    const subtitle = document.getElementById('console-subtitle');

    if (running) {
        if (btnStart) btnStart.classList.add('d-none');
        if (btnStop) btnStop.classList.remove('d-none');
        if (configLayer) configLayer.classList.add('d-none');
        if (monitorLayer) monitorLayer.classList.remove('d-none');
        if (subtitle) {
            subtitle.innerHTML = '<span class="text-primary animate-pulse">Sincronización en curso...</span>';
        }
    } else {
        if (btnStart) btnStart.classList.remove('d-none');
        if (btnStop) btnStop.classList.add('d-none');
        if (configLayer) configLayer.classList.remove('d-none');
        if (monitorLayer) monitorLayer.classList.add('d-none');
        if (subtitle) {
            subtitle.textContent = 'Lista para operar • v3.5';
        }
    }

    isSyncing = running;
}

function updateProgress(status) {
    const progressBar = document.getElementById('sync-progressbar');
    const percentLabel = document.getElementById('sync-percent');
    const msgLabel = document.getElementById('sync-message');
    const phaseLabel = document.getElementById('sync-phase');

    const pct = status.progress || 0;
    const msg = status.message || 'Esperando...';

    // Agregar al log si el mensaje cambió
    if (msg !== lastSyncMessage && msg !== 'Esperando...') {
        logMessage(msg, 'info');
        lastSyncMessage = msg;
    }

    if (progressBar) {
        progressBar.style.setProperty('--progress', pct + '%');
        progressBar.style.width = pct + '%';
    }
    if (percentLabel) percentLabel.innerText = pct + '%';
    if (msgLabel) msgLabel.innerText = msg;
    if (phaseLabel) {
        const type = getSyncTypeLabel(status.type || 'all');
        phaseLabel.innerText = `Tipo: ${type} | Estado: ${status.status || 'desconocido'}`;
    }

    const typeBadge = document.getElementById('sync-type-badge');
    if (typeBadge) {
        typeBadge.innerText = `Tipo: ${getSyncTypeLabel(status.type || 'all')}`;
    }

    // Update stats
    if (status.total_processed !== undefined) {
        processedCount = status.total_processed;
        document.getElementById('stat-processed').textContent = status.total_processed.toLocaleString();
    }
    if (status.total_updated !== undefined) {
        const el = document.getElementById('stat-updated');
        if (el) el.textContent = status.total_updated.toLocaleString();
    }
    if (status.total_errors !== undefined) {
        document.getElementById('stat-errors').textContent = status.total_errors || 0;
    }

    // Actualizar job history badge if available
    loadJobHistory();

    // Actualizar tiempo de última sincronización si existe
    if (status.end_time) {
        const timeEl = document.getElementById('last-sync-time');
        if (timeEl) {
            // Intentar formatear si es timestamp o string ISO
            try {
                // Si es numérico (timestamp unix)
                const date = !isNaN(status.end_time) && Number(status.end_time) > 10000
                    ? new Date(Number(status.end_time) * 1000)
                    : new Date(status.end_time);

                if (!isNaN(date.getTime())) {
                    timeEl.textContent = date.toLocaleString('es-PY');
                } else {
                    timeEl.textContent = status.end_time;
                }
            } catch (e) {
                timeEl.textContent = status.end_time;
            }
        }
    }
}

function updatePerformanceMetrics(status) {
    const elapsedEl = document.getElementById('perf-elapsed');
    const rateEl = document.getElementById('perf-rate');
    const now = Date.now();

    // 1. Manejo de tiempo transcurrido
    if (!syncStartTime && status.start_time && isSyncing) {
        // Soporta tanto string ISO/MySQL como timestamp numérico
        const timeVal = status.start_time.toString().replace(/-/g, "/");
        const t = isNaN(timeVal) ? new Date(timeVal) : new Date(Number(timeVal) * 1000);
        if (!isNaN(t.getTime())) syncStartTime = t.getTime();
    }

    if (syncStartTime && isSyncing) {
        const elapsedSec = Math.max(0, (now - syncStartTime) / 1000);
        if (elapsedEl) elapsedEl.textContent = formatDuration(elapsedSec);

        // 2. Cálculo de Velocidad Instantánea (Delta / Time)
        const currentTotal = status.total_processed || 0;
        
        if (lastPulseTime > 0 && now > lastPulseTime) {
            const deltaTime = (now - lastPulseTime) / 1000;
            const deltaProcessed = currentTotal - lastProcessedCount;
            
            if (deltaProcessed >= 0) {
                const currentRate = deltaProcessed / deltaTime;
                
                // Suavizado (Moving Average de los últimos 5 puntos)
                rateHistory.push(currentRate);
                if (rateHistory.length > 5) rateHistory.shift();
                
                instantRate = rateHistory.reduce((a, b) => a + b, 0) / rateHistory.length;
            }
        }

        if (rateEl) {
            if (instantRate > 0) {
                rateEl.textContent = `${instantRate.toFixed(1)}/s`;
                // Efecto de color según velocidad
                rateEl.style.color = instantRate > 50 ? '#10b981' : (instantRate > 10 ? '#38bdf8' : '#94a3b8');
            } else if (currentTotal > 0 && elapsedSec > 0) {
                // Fallback a promedio si no hay pulso reciente
                const avgRate = (currentTotal / elapsedSec).toFixed(1);
                rateEl.textContent = `${avgRate}/s`;
            } else {
                rateEl.textContent = '--';
            }
        }

        lastProcessedCount = currentTotal;
        lastPulseTime = now;

    } else if (!isSyncing) {
        // Reset
        syncStartTime = null;
        lastPulseTime = 0;
        lastProcessedCount = 0;
        rateHistory = [];
        instantRate = 0;
        if (elapsedEl) elapsedEl.textContent = '--';
        if (rateEl) {
            rateEl.textContent = '--';
            rateEl.style.color = '';
        }
    }
}

function loadJobHistory() {
    const baseUrl = getBaseUrl();
    const tableBody = document.getElementById('job-history-body');
    if (!tableBody) return;

    fetchJson(baseUrl + 'moodle/jobs/status', {
        method: 'GET'
    })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            const isSuccess = data.success === true || data.status === 'success';
            if (!isSuccess || !data.data) {
                throw new Error(data.message || 'No se recibió historial de jobs');
            }

            const jobs = data.data;
            if (!Array.isArray(jobs) || jobs.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay jobs recientes</td></tr>';
                return;
            }

            tableBody.innerHTML = jobs.map(job => {
                const syncType = job.sync_type ? job.sync_type.replace(/_/g, ' ') : 'N/A';
                const progress = typeof job.progress === 'number' ? `${job.progress}%` : 'N/A';
                const started = job.started_at ? new Date(job.started_at).toLocaleString('es-PY') : '-';
                const error = job.error ? `<span class="text-danger" title="${job.error}">${job.error}</span>` : '-';

                return `
                    <tr>
                        <td>${job.id}</td>
                        <td>${syncType}</td>
                        <td>${job.entity || '-'}</td>
                        <td>${job.status || '-'}</td>
                        <td>${progress}</td>
                        <td>${started}</td>
                        <td>${error}</td>
                    </tr>
                `;
            }).join('');
        })
        .catch(err => {
            console.error('Error cargando historial de jobs:', err);
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error cargando historial: ${err.message}</td></tr>`;
        });
}

let lastLoggedStatus = null;

function updateStatusBadge(status) {
    const badge = document.getElementById('sync-status-badge');
    if (!badge) return;

    const statusText = badge.querySelector('.status-text');

    let className = 'sync-status-badge ';
    let text = 'Desconocido';

    switch (status) {
        case 'running':
            className += 'running';
            text = 'Sincronizando';
            break;
        case 'stopping':
            className += 'stopping';
            text = 'Deteniendo...';
            if (lastLoggedStatus !== 'stopping') {
                logMessage('Esperando que el lote actual termine para detener...', 'warning');
                lastLoggedStatus = 'stopping';
            }
            break;
        case 'stopped':
            className += 'idle';
            text = 'Detenido';
            if (lastLoggedStatus !== 'stopped') {
                logMessage('Sincronización detenida correctamente por el usuario', 'warning');
                lastLoggedStatus = 'stopped';
            }
            break;
        case 'completed':
            className += 'completed';
            text = 'Completado';
            // Solo loguear una vez
            if (lastLoggedStatus !== 'completed') {
                logMessage('Sincronización completada exitosamente', 'success');
                lastLoggedStatus = 'completed';
            }
            break;
        case 'error':
            className += 'error';
            text = 'Error';
            if (lastLoggedStatus !== 'error') {
                logMessage('Sincronización terminó con errores', 'error');
                lastLoggedStatus = 'error';
            }
            break;
        default:
            className += 'idle';
            text = 'Inactivo';
            lastLoggedStatus = null;
    }

    badge.className = className;
    if (statusText) statusText.textContent = text;
}
