(function () {
  "use strict";

  function initActivityMonitoring() {
    console.log('[Activity Monitoring] initActivityMonitoring called');
    
    const { fetchJson, escapeHtml, setStatusById, showError, showSuccess, renderEmptyState } = window.dmportal || {};

    const loadBtn = document.getElementById('loadActivityLogs');
    const exportBtn = document.getElementById('exportActivityLogs');
    const clearBtn = document.getElementById('clearActivityFilters');
    const statusDiv = document.getElementById('activityLogsStatus');
    const summaryDiv = document.getElementById('activityLogsSummary');
    const tableWrap = document.getElementById('activityLogsTableWrap');
    const tbody = document.querySelector('#activityLogsTable tbody');
    const paginationDiv = document.getElementById('activityLogsPagination');
    
    console.log('[Activity Monitoring] Elements found:', { loadBtn, exportBtn, statusDiv });
    
    // Check if we're on the right page
    if (!loadBtn || !exportBtn || !statusDiv) {
      console.log('[Activity Monitoring] Not on activity monitoring page, skipping initialization');
      return;
    }
    
    // Filter inputs
    const dateFromInput = document.getElementById('activityDateFrom');
    const dateToInput = document.getElementById('activityDateTo');
    const usernameInput = document.getElementById('activityUsername');
    const roleSelect = document.getElementById('activityRole');
    const categorySelect = document.getElementById('activityCategory');
    const actionTypeSelect = document.getElementById('activityActionType');
    const resultSelect = document.getElementById('activityResult');
    const searchInput = document.getElementById('activitySearch');
    
    console.log('[Activity Monitoring] Filter inputs:', { dateFromInput, dateToInput, categorySelect, actionTypeSelect });
    
    if (!dateFromInput || !dateToInput || !categorySelect || !actionTypeSelect) {
      console.error('[Activity Monitoring] Missing required filter elements');
      return;
    }
    
    console.log('[Activity Monitoring] Initialization successful');
    
    let currentLogs = [];
    let filterOptions = {};
    let currentOffset = 0;
    const limit = 100;
    let isLoading = false; // Prevent multiple simultaneous loads

    // Initialize date range (last 7 days)
    function initDateRange() {
      const today = new Date();
      const weekAgo = new Date(today);
      weekAgo.setDate(weekAgo.getDate() - 7);
      
      dateToInput.valueAsDate = today;
      dateFromInput.valueAsDate = weekAgo;
    }

    // Get current filters
    function getFilters() {
      const filters = {};
      
      if (dateFromInput.value) filters.dateFrom = dateFromInput.value;
      if (dateToInput.value) filters.dateTo = dateToInput.value;
      if (usernameInput.value.trim()) filters.username = usernameInput.value.trim();
      if (roleSelect.value && roleSelect.value !== 'all') filters.role = roleSelect.value;
      if (categorySelect.value && categorySelect.value !== 'all') filters.actionCategory = categorySelect.value;
      if (actionTypeSelect.value && actionTypeSelect.value !== 'all') filters.actionType = actionTypeSelect.value;
      if (resultSelect.value && resultSelect.value !== 'all') filters.result = resultSelect.value;
      if (searchInput.value.trim()) filters.search = searchInput.value.trim();
      
      filters.limit = limit;
      filters.offset = currentOffset;
      
      return filters;
    }

    // Build query string from filters
    function buildQueryString(filters) {
      const params = new URLSearchParams();
      for (const key in filters) {
        params.append(key, filters[key]);
      }
      return params.toString();
    }

    // Load activity logs
    async function loadLogs(resetOffset = true) {
      console.log('[Activity Monitoring] loadLogs() called, isLoading:', isLoading);
      console.trace('[Activity Monitoring] Call stack:');
      
      if (isLoading) {
        console.log('[Activity Monitoring] Already loading, skipping');
        return;
      }
      
      if (resetOffset) {
        currentOffset = 0;
      }

      try {
        isLoading = true;
        loadBtn.disabled = true;
        setStatusById('activityLogsStatus', 'Loading activity logs...', 'info');

        const filters = getFilters();
        const queryString = buildQueryString(filters);
        const payload = await fetchJson(`php/get_activity_logs.php?${queryString}`);

        if (!payload.success) {
          throw new Error(payload.error || 'Failed to load logs');
        }

        currentLogs = payload.data || [];
        filterOptions = payload.filterOptions || {};

        // Update filter dropdowns
        updateFilterDropdowns();

        // Render logs
        renderLogs();
        renderSummary(payload.total || 0);

        // Show/hide sections
        if (currentLogs.length === 0) {
          tableWrap.style.display = 'none';
          summaryDiv.style.display = 'none';
          setStatusById('activityLogsStatus', 'No activity logs found with current filters.', '');
        } else {
          tableWrap.style.display = 'block';
          summaryDiv.style.display = 'block';
          setStatusById('activityLogsStatus', '', '');
        }

        // Enable export button
        exportBtn.disabled = currentLogs.length === 0;

        // Update pagination
        updatePagination(payload.total || 0);

      } catch (err) {
        showError(err.message || 'Failed to load activity logs.');
        setStatusById('activityLogsStatus', err.message || 'Failed to load activity logs.', 'error');
      } finally {
        loadBtn.disabled = false;
        isLoading = false;
      }
    }

    // Update filter dropdowns with available options
    function updateFilterDropdowns() {
      console.log('[Activity Monitoring] updateFilterDropdowns called');
      console.log('[Activity Monitoring] filterOptions:', filterOptions);
      
      // Categories
      const categorySelectEl = document.getElementById('activityCategory');
      console.log('[Activity Monitoring] categorySelectEl:', categorySelectEl);
      
      if (!categorySelectEl) {
        console.warn('[Activity Monitoring] categorySelectEl is null, skipping update');
        return;
      }
      
      if (filterOptions.actionCategories && filterOptions.actionCategories.length > 0) {
        console.log('[Activity Monitoring] Updating categories dropdown');
        const currentCategory = categorySelectEl.value;
        categorySelectEl.innerHTML = '<option value="all">All Categories</option>';
        
        filterOptions.actionCategories.forEach(cat => {
          const option = document.createElement('option');
          option.value = cat;
          option.textContent = formatCategory(cat);
          categorySelectEl.appendChild(option);
        });
        
        if (currentCategory && filterOptions.actionCategories.includes(currentCategory)) {
          categorySelectEl.value = currentCategory;
        }
      }

      // Action Types
      const actionTypeSelectEl = document.getElementById('activityActionType');
      console.log('[Activity Monitoring] actionTypeSelectEl:', actionTypeSelectEl);
      
      if (!actionTypeSelectEl) {
        console.warn('[Activity Monitoring] actionTypeSelectEl is null, skipping update');
        return;
      }
      
      if (filterOptions.actionTypes && filterOptions.actionTypes.length > 0) {
        console.log('[Activity Monitoring] Updating action types dropdown');
        const currentActionType = actionTypeSelectEl.value;
        actionTypeSelectEl.innerHTML = '<option value="all">All Actions</option>';
        
        filterOptions.actionTypes.forEach(type => {
          const option = document.createElement('option');
          option.value = type;
          option.textContent = formatActionType(type);
          actionTypeSelectEl.appendChild(option);
        });
        
        if (currentActionType && filterOptions.actionTypes.includes(currentActionType)) {
          actionTypeSelectEl.value = currentActionType;
        }
      }
      
      console.log('[Activity Monitoring] updateFilterDropdowns complete');
    }

    // Format category for display
    function formatCategory(cat) {
      const map = {
        'auth': 'Authentication',
        'crud': 'CRUD Operations',
        'file': 'File Operations',
        'view': 'Page Views',
        'system': 'System Settings',
      };
      return map[cat] || cat.charAt(0).toUpperCase() + cat.slice(1);
    }

    // Format action type for display
    function formatActionType(type) {
      return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    // Render logs table
    function renderLogs() {
      if (!tbody) return;

      tbody.innerHTML = '';

      if (currentLogs.length === 0) {
        // Don't use renderEmptyState on .table-wrap - it destroys filter elements!
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 10; // Updated from 8 to 10 (added Time column)
        td.style.cssText = 'text-align: center; padding: 3rem 1rem; color: var(--text-secondary);';
        td.innerHTML = `
          <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
          <div style="font-size: 1.1rem; font-weight: 500; margin-bottom: 0.5rem;">No activity logs found</div>
          <div style="font-size: 0.95rem;">Try adjusting your filters or date range</div>
        `;
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
      }

      currentLogs.forEach(log => {
        const tr = document.createElement('tr');

        // Date/Time - cleaner format
        const tdDate = document.createElement('td');
        tdDate.style.cssText = 'white-space: nowrap; font-size: 0.9rem;';
        const date = new Date(log.created_at);
        const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        tdDate.innerHTML = `<div style="font-weight: 600;">${dateStr}</div><div class="muted" style="font-size: 0.85rem;">${timeStr}</div>`;
        tr.appendChild(tdDate);

        // Username - badge style
        const tdUsername = document.createElement('td');
        tdUsername.innerHTML = `<span style="display: inline-block; padding: 4px 10px; background: var(--surface-1); border: 1px solid var(--card-border); border-radius: 8px; font-weight: 600; font-size: 0.9rem;">${escapeHtml(log.username || 'N/A')}</span>`;
        tr.appendChild(tdUsername);

        // Role - colored badge
        const tdRole = document.createElement('td');
        const roleBadge = document.createElement('span');
        roleBadge.className = 'badge';
        roleBadge.style.cssText = getRoleStyle(log.role) + '; text-transform: capitalize;';
        roleBadge.textContent = log.role || 'N/A';
        tdRole.appendChild(roleBadge);
        tr.appendChild(tdRole);

        // Category - icon + text
        const tdCategory = document.createElement('td');
        const categoryIcons = {
          'auth': '🔐',
          'crud': '✏️',
          'file': '📁',
          'view': '👁️',
          'admin_view': '⚙️',
          'api': '🔌',
          'system': '🖥️'
        };
        const icon = categoryIcons[log.action_category] || '📋';
        tdCategory.innerHTML = `<span style="font-size: 0.9rem;">${icon} ${formatCategory(log.action_category)}</span>`;
        tr.appendChild(tdCategory);

        // Action - formatted
        const tdAction = document.createElement('td');
        tdAction.style.cssText = 'font-size: 0.9rem; font-weight: 500;';
        tdAction.textContent = formatActionType(log.action_type);
        tr.appendChild(tdAction);

        // Target - compact and clean
        const tdTarget = document.createElement('td');
        tdTarget.style.cssText = 'max-width: 250px;';
        if (log.target_name) {
          const targetDiv = document.createElement('div');
          targetDiv.style.cssText = 'font-size: 0.9rem; line-height: 1.4;';
          
          // Main target name - limit length
          const targetName = log.target_name.length > 40 ? log.target_name.substring(0, 40) + '...' : log.target_name;
          const nameDiv = document.createElement('div');
          nameDiv.style.fontWeight = '600';
          nameDiv.textContent = targetName;
          targetDiv.appendChild(nameDiv);
          
          if (log.target_type) {
            const typeSpan = document.createElement('div');
            typeSpan.className = 'muted';
            typeSpan.style.cssText = 'font-size: 0.75rem; margin-top: 2px;';
            typeSpan.textContent = log.target_type + (log.target_id ? ' #' + log.target_id : '');
            targetDiv.appendChild(typeSpan);
          }
          
          tdTarget.appendChild(targetDiv);
        } else {
          tdTarget.innerHTML = '<span class="muted">—</span>';
        }
        tr.appendChild(tdTarget);

        // Response Time (color-coded)
        const tdResponseTime = document.createElement('td');
        tdResponseTime.style.cssText = 'text-align: right; font-family: monospace; font-size: 0.9rem; white-space: nowrap;';
        if (log.response_time_ms !== null && log.response_time_ms !== undefined) {
          const ms = parseInt(log.response_time_ms);
          let color = 'var(--success)'; // green for fast
          let icon = '🟢';
          if (ms > 1000) {
            color = 'var(--danger)'; // red for slow (>1s)
            icon = '🔴';
          } else if (ms > 500) {
            color = '#ff9800'; // orange for medium (>500ms)
            icon = '🟠';
          }
          tdResponseTime.innerHTML = `<span style="color: ${color}; font-weight: 600;">${icon} ${ms}ms</span>`;
        } else {
          tdResponseTime.innerHTML = '<span class="muted">—</span>';
        }
        tr.appendChild(tdResponseTime);

        // Details - collapsible and cleaner
        const tdDetails = document.createElement('td');
        tdDetails.style.cssText = 'font-size: 0.85rem; max-width: 400px;';
        if (log.details) {
          try {
            const detailsObj = JSON.parse(log.details);
            
            // Create a more readable summary
            const lines = [];
            
            // Priority keys to show
            const priorityKeys = ['course_name', 'subject_code', 'week', 'day', 'slot', 'reason', 'master_key_used', 'marked_by_role'];
            priorityKeys.forEach(key => {
              if (detailsObj[key] !== undefined) {
                lines.push(`${key}: ${detailsObj[key]}`);
              }
            });
            
            // Show other keys (exclude noisy ones)
            const excludeKeys = ['request_method', 'request_time', 'session_id', 'referer', 'post_data', ...priorityKeys];
            Object.keys(detailsObj).forEach(key => {
              if (!excludeKeys.includes(key) && detailsObj[key] !== undefined && detailsObj[key] !== null) {
                lines.push(`${key}: ${detailsObj[key]}`);
              }
            });
            
            const summaryText = lines.slice(0, 3).join(' • '); // Show first 3 items
            const fullText = JSON.stringify(detailsObj, null, 2);
            
            if (summaryText) {
              tdDetails.innerHTML = `
                <div style="cursor: help; line-height: 1.5;" title="${escapeHtml(fullText)}">
                  <div style="color: var(--text);">${escapeHtml(summaryText)}</div>
                  ${lines.length > 3 ? `<div class="muted" style="font-size: 0.75rem; margin-top: 4px;">+${lines.length - 3} more (hover for full details)</div>` : ''}
                </div>
              `;
            } else {
              tdDetails.innerHTML = `<span class="muted" style="cursor: help;" title="${escapeHtml(fullText)}">Hover for details</span>`;
            }
          } catch (e) {
            tdDetails.textContent = log.details.substring(0, 100) + '...';
          }
        } else {
          tdDetails.innerHTML = '<span class="muted">—</span>';
        }
        tr.appendChild(tdDetails);

        // IP - simplified
        const tdIP = document.createElement('td');
        tdIP.style.cssText = 'font-family: monospace; font-size: 0.85rem; color: var(--muted);';
        const ip = log.ip_address || 'unknown';
        tdIP.textContent = ip === '::1' ? 'localhost' : ip;
        tr.appendChild(tdIP);

        // Result - icon badge
        const tdResult = document.createElement('td');
        const resultBadge = document.createElement('span');
        resultBadge.style.cssText = 'display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600;';
        
        if (log.result === 'success') {
          resultBadge.style.background = 'rgba(var(--success-rgb), 0.15)';
          resultBadge.style.color = 'var(--success)';
          resultBadge.style.border = '1px solid rgba(var(--success-rgb), 0.3)';
          resultBadge.innerHTML = '✓ Success';
        } else if (log.result === 'failure') {
          resultBadge.style.background = 'rgba(var(--danger-rgb), 0.15)';
          resultBadge.style.color = 'var(--danger)';
          resultBadge.style.border = '1px solid rgba(var(--danger-rgb), 0.3)';
          resultBadge.innerHTML = '✗ Failed';
        } else {
          resultBadge.style.background = 'rgba(255, 152, 0, 0.15)';
          resultBadge.style.color = '#ff9800';
          resultBadge.style.border = '1px solid rgba(255, 152, 0, 0.3)';
          resultBadge.innerHTML = '⚠ Error';
        }
        
        tdResult.appendChild(resultBadge);
        tr.appendChild(tdResult);

        tbody.appendChild(tr);
      });
    }

    // Format date/time for display
    function formatDateTime(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }

    // Get role badge style
    function getRoleStyle(role) {
      const styles = {
        'admin': 'background:rgba(255,107,107,0.15); color:#ff6b6b; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:600;',
        'management': 'background:rgba(92,198,255,0.15); color:#5cc6ff; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:600;',
        'teacher': 'background:rgba(65,217,122,0.15); color:#41d97a; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:600;',
        'student': 'background:rgba(207,232,255,0.15); color:#cfe8ff; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:600;',
      };
      return styles[role] || styles['student'];
    }

    // Get result badge style
    function getResultStyle(result) {
      const styles = {
        'success': 'background:rgba(65,217,122,0.15); color:#41d97a; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:600;',
        'failure': 'background:rgba(255,193,7,0.15); color:#ffc107; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:600;',
        'error': 'background:rgba(255,107,107,0.15); color:#ff6b6b; padding:4px 10px; border-radius:999px; font-size:0.8rem; font-weight:600;',
      };
      return styles[result] || styles['success'];
    }

    // Render summary stats
    function renderSummary(total) {
      document.getElementById('activityLogsTotal').textContent = total.toLocaleString();

      const successCount = currentLogs.filter(l => l.result === 'success').length;
      const failureCount = currentLogs.filter(l => l.result === 'failure' || l.result === 'error').length;
      const uniqueUsers = new Set(currentLogs.map(l => l.username).filter(Boolean)).size;

      document.getElementById('activityLogsSuccess').textContent = successCount.toLocaleString();
      document.getElementById('activityLogsFailures').textContent = failureCount.toLocaleString();
      document.getElementById('activityLogsUsers').textContent = uniqueUsers.toLocaleString();
    }

    // Update pagination
    function updatePagination(total) {
      const pageInfo = document.getElementById('activityLogsPageInfo');
      const prevBtn = document.getElementById('activityLogsPrevPage');
      const nextBtn = document.getElementById('activityLogsNextPage');

      const start = currentOffset + 1;
      const end = Math.min(currentOffset + limit, total);

      pageInfo.textContent = `Showing ${start.toLocaleString()}–${end.toLocaleString()} of ${total.toLocaleString()}`;

      prevBtn.disabled = currentOffset === 0;
      nextBtn.disabled = end >= total;
    }

    // Export to Excel
    async function exportLogs() {
      try {
        exportBtn.disabled = true;
        setStatusById('activityLogsStatus', 'Generating Excel file...', 'info');

        const filters = getFilters();
        // Remove pagination for export (get all)
        delete filters.limit;
        delete filters.offset;

        const queryString = buildQueryString(filters);
        const url = `php/export_activity_logs.php?${queryString}`;

        // Trigger download
        window.location.href = url;

        showSuccess('Excel export started. Download should begin shortly.');
        setStatusById('activityLogsStatus', '', '');

      } catch (err) {
        showError(err.message || 'Failed to export logs.');
        setStatusById('activityLogsStatus', err.message || 'Failed to export logs.', 'error');
      } finally {
        exportBtn.disabled = false;
      }
    }

    // Clear filters
    function clearFilters() {
      initDateRange();
      usernameInput.value = '';
      roleSelect.value = 'all';
      categorySelect.value = 'all';
      actionTypeSelect.value = 'all';
      resultSelect.value = 'all';
      searchInput.value = '';
      currentOffset = 0;

      // Clear results
      tableWrap.style.display = 'none';
      summaryDiv.style.display = 'none';
      exportBtn.disabled = true;
      setStatusById('activityLogsStatus', '', '');
    }

    // Event listeners
    loadBtn?.addEventListener('click', () => loadLogs(true));
    exportBtn?.addEventListener('click', exportLogs);
    clearBtn?.addEventListener('click', clearFilters);

    // Pagination
    document.getElementById('activityLogsPrevPage')?.addEventListener('click', () => {
      if (currentOffset > 0) {
        currentOffset -= limit;
        loadLogs(false);
      }
    });

    document.getElementById('activityLogsNextPage')?.addEventListener('click', () => {
      currentOffset += limit;
      loadLogs(false);
    });

    // Enter key on search fields
    [usernameInput, searchInput].forEach(input => {
      input?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          loadLogs(true);
        }
      });
    });

    // Initialize
    initDateRange();
  }

  // Register globally
  window.dmportal = window.dmportal || {};
  window.dmportal.initActivityMonitoring = initActivityMonitoring;
})();
