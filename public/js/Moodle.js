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
let processedCount = 0;

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
    if (btnSyncStart) btnSyncStart.addEventListener('click', startGlobalSync);

    const btnSyncStop = document.getElementById('btn-sync-stop');
    if (btnSyncStop) btnSyncStop.addEventListener('click', stopGlobalSync);

    const btnResetTotal = document.getElementById('btn-reset-processes');
    if (btnResetTotal) btnResetTotal.addEventListener('click', resetAllProcesses);

    // Log controls
    const btnExportLog = document.getElementById('btn-export-log');
    if (btnExportLog) btnExportLog.addEventListener('click', exportLog);

    const btnClearLog = document.getElementById('btn-clear-log');
    if (btnClearLog) btnClearLog.addEventListener('click', clearLog);

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
    entry.innerHTML = `
        <span class="log-time">${getTimestamp()}</span>
        <span class="log-msg">${msg}</span>
    `;

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

// =====================================
// HEALTH CHECK
// =====================================
function checkHealth() {
    const indicator = document.getElementById('health-indicator');
    const dot = indicator?.querySelector('.health-dot');

    // Set loading state
    if (indicator) indicator.className = 'health-indicator loading';
    if (dot) dot.className = 'health-dot loading';

    fetch(window.BASE_URL + 'moodle/health', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
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

            if (data.success && data.data) {
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

            } else if (!data.success) {
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
            fetch(window.BASE_URL + 'moodle/reset-circuit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `csrf_token=${encodeURIComponent(csrfToken)}`
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Reseteado', 'Circuit breaker reseteado correctamente', 'success');
                        logMessage('Circuit breaker reseteado manualmente', 'warning');
                        checkHealth();
                    } else {
                        throw new Error(data.message || 'Error al resetear');
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
            fetch(window.BASE_URL + 'moodle/reset-processes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
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
function startGlobalSync() {
    const csrfToken = getCsrfToken();
    const syncType = document.getElementById('sync-type')?.value || 'all';
    const forceSync = document.getElementById('sync-force')?.checked || false;
    const regenPasswords = document.getElementById('sync-regenerate-passwords')?.checked || false;

    const typeLabels = {
        'all': 'completa',
        'delta': 'incremental (últimas 24h)',
        'categories': 'de categorías',
        'courses': 'de cursos',
        'users': 'de usuarios',
        'enrollments': 'de matrículas',
        'cohorts': 'de cohortes',
        'grades': 'de calificaciones'
    };

    const label = typeLabels[syncType] || syncType;

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

            fetch(window.BASE_URL + 'moodle/sync/asyncStart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
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
                    }
                })
                .catch(err => {
                    Swal.fire('Error de Red', err.message, 'error');
                    logMessage(`Error de red: ${err.message}`, 'error');
                    updateUIState(false);
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

            fetch(window.BASE_URL + 'moodle/sync/asyncStop', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
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
                });
        }
    });
}

function syncEntity(entity) {
    const csrfToken = getCsrfToken();
    const forceSync = document.getElementById('sync-force')?.checked || false;

    logMessage(`Iniciando sincronización de ${entity}...`, 'info');

    fetch(window.BASE_URL + `moodle/sync/${entity}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `csrf_token=${encodeURIComponent(csrfToken)}&force=${forceSync ? 1 : 0}`
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Sincronización Iniciada',
                    html: `<p>${data.message}</p><small class="text-muted">Job ID: ${data.job_id || 'N/A'}</small>`,
                    icon: 'success',
                    timer: 2500,
                    showConfirmButton: false
                });
                logMessage(`${entity}: ${data.message}`, 'success');
                startPolling();
            } else {
                Swal.fire('Error', data.message, 'error');
                logMessage(`${entity}: Error - ${data.message}`, 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', err.message, 'error');
            logMessage(`${entity}: Error de red - ${err.message}`, 'error');
        });
}

// =====================================
// POLLING & STATUS
// =====================================
function checkGlobalStatus() {
    // Asegurar que BASE_URL exista
    const baseUrl = window.BASE_URL || '/eduma2/';

    fetch(baseUrl + 'moodle/sync/getAsyncStatus', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
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

    const url = window.BASE_URL + 'moodle/sync/streamProgress';
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
        // Silenciosamente reintenta o fallback si el server cierra el stream
        if (eventSource.readyState === EventSource.CLOSED) {
            console.log('SSE connection closed, waiting to reconnect if needed...');
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

    if (progressBar) {
        progressBar.style.setProperty('--progress', pct + '%');
        progressBar.style.width = pct + '%';
    }
    if (percentLabel) percentLabel.innerText = pct + '%';
    if (msgLabel) msgLabel.innerText = msg;
    if (phaseLabel) {
        const type = status.type || 'all';
        phaseLabel.innerText = `Tipo: ${type} | Estado: ${status.status || 'desconocido'}`;
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

    // Intentar recuperar tiempo de inicio del servidor si no tenemos el local
    if (!syncStartTime && status.start_time && isSyncing) {
        // Asumimos formato SQL YYYY-MM-DD HH:MM:SS o timestamp
        const t = new Date(status.start_time.replace(/-/g, "/")); // replace para compatibilidad safari/firefox a veces
        if (!isNaN(t.getTime())) {
            syncStartTime = t.getTime();
        }
    }

    if (syncStartTime && isSyncing) {
        const elapsedSec = Math.max(0, (Date.now() - syncStartTime) / 1000);
        if (elapsedEl) elapsedEl.textContent = formatDuration(elapsedSec);

        // Usar total_processed si está disponible, sino processedCount local
        const total = status.total_processed !== undefined ? status.total_processed : processedCount;

        if (rateEl && total > 0 && elapsedSec > 0) {
            const rate = (total / elapsedSec).toFixed(1);
            rateEl.textContent = `${rate}/s`;
        }
    } else if (!isSyncing) {
        // Resetear si no está corriendo
        syncStartTime = null;
        if (elapsedEl) elapsedEl.textContent = '--';
        if (rateEl) rateEl.textContent = '--';
    }
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
            className += 'running';
            text = 'Deteniendo';
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
