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
        td.colSpan = 8;
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

        // Date/Time
        const tdDate = document.createElement('td');
        tdDate.textContent = formatDateTime(log.created_at);
        tr.appendChild(tdDate);

        // Username
        const tdUsername = document.createElement('td');
        tdUsername.textContent = log.username || 'N/A';
        tr.appendChild(tdUsername);

        // Role
        const tdRole = document.createElement('td');
        const roleBadge = document.createElement('span');
        roleBadge.className = 'badge';
        roleBadge.style.cssText = getRoleStyle(log.role);
        roleBadge.textContent = log.role || 'N/A';
        tdRole.appendChild(roleBadge);
        tr.appendChild(tdRole);

        // Category
        const tdCategory = document.createElement('td');
        tdCategory.textContent = formatCategory(log.action_category);
        tr.appendChild(tdCategory);

        // Action
        const tdAction = document.createElement('td');
        tdAction.textContent = formatActionType(log.action_type);
        tr.appendChild(tdAction);

        // Target
        const tdTarget = document.createElement('td');
        if (log.target_name) {
          const targetDiv = document.createElement('div');
          targetDiv.style.cssText = 'font-size:0.9rem;';
          targetDiv.textContent = log.target_name;
          
          if (log.target_type) {
            const typeSpan = document.createElement('div');
            typeSpan.className = 'muted';
            typeSpan.style.cssText = 'font-size:0.75rem; margin-top:2px;';
            typeSpan.textContent = log.target_type + (log.target_id ? ' #' + log.target_id : '');
            targetDiv.appendChild(typeSpan);
          }
          
          tdTarget.appendChild(targetDiv);
        } else {
          tdTarget.textContent = '—';
        }
        tr.appendChild(tdTarget);

        // Details
        const tdDetails = document.createElement('td');
        tdDetails.style.cssText = 'font-size:0.85rem;';
        if (log.details) {
          try {
            const detailsObj = JSON.parse(log.details);
            tdDetails.textContent = JSON.stringify(detailsObj, null, 2);
            tdDetails.style.whiteSpace = 'pre-wrap';
            tdDetails.style.fontFamily = 'monospace';
          } catch {
            tdDetails.textContent = log.details;
          }
        } else {
          tdDetails.textContent = '—';
        }
        tr.appendChild(tdDetails);

        // IP
        const tdIP = document.createElement('td');
        tdIP.textContent = log.ip_address || 'unknown';
        tdIP.style.cssText = 'font-family:monospace; font-size:0.85rem;';
        tr.appendChild(tdIP);

        // Result
        const tdResult = document.createElement('td');
        const resultBadge = document.createElement('span');
        resultBadge.className = 'badge';
        resultBadge.style.cssText = getResultStyle(log.result);
        resultBadge.textContent = log.result;
        tdResult.appendChild(resultBadge);
        tr.appendChild(tdResult);

        tbody.appendChild(tr);
      });
    }

    // Format date/time
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
