document.addEventListener('DOMContentLoaded', () => {
    let currentWeeklyRange = '7d';
    let currentBusinessRange = '7d';
    let weeklyPerformanceChart;
    let businessTrendChart;

    // Helper function to convert range codes to friendly text
    function getFriendlyRangeText(range) {
        const ranges = {
            '1d': '1 day',
            '7d': '7 days',
            '30d': '30 days',
            '90d': '3 months',
            '180d': '6 months',
            '365d': '1 year',
            '730d': '2 years',
            '1095d': '3 years',
            '1460d': '4 years'
        };
        return ranges[range] || '7 days';
    }

    // Initialize weekly performance chart
    function renderWeeklyPerformanceChart(data, rangeText) {
        const canvas = document.getElementById('weekly-performance-chart');
        if (!canvas) {
            console.error('Weekly performance chart canvas not found');
            return;
        }

        const ctx = canvas.getContext('2d');
        document.getElementById('weekly-performance-range').textContent = getFriendlyRangeText(rangeText);

        // Destroy previous chart instance if exists
        if (weeklyPerformanceChart) {
            weeklyPerformanceChart.destroy();
        }

        // Prepare chart data with fallback values
        const labels = data?.labels || Array.from({length: 7}, (_, i) => `Day ${i+1}`);
        const sales = data?.sales || Array.from({length: 7}, () => Math.floor(Math.random() * 2000) + 1000);
        const expenses = data?.expenses || Array.from({length: 7}, () => Math.floor(Math.random() * 1000) + 500);

        weeklyPerformanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sales',
                        data: sales,
                        borderColor: '#48BB78',
                        backgroundColor: 'rgba(72, 187, 120, 0.2)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Expenses',
                        data: expenses,
                        borderColor: 'rgba(255, 0, 0, 0.53)',
                        backgroundColor: 'rgba(255, 0, 0, 0.32)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: LKR ${context.raw.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'LKR ' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    }

    // Initialize business trend chart with proper profit calculation
    function renderBusinessTrendChart(data, rangeText) {
        const canvas = document.getElementById('business-trend-chart');
        if (!canvas) {
            console.error('Business trend chart canvas not found');
            return;
        }

        const ctx = canvas.getContext('2d');
        document.getElementById('business-trend-range').textContent = getFriendlyRangeText(rangeText);

        // Destroy previous chart instance if exists
        if (businessTrendChart) {
            businessTrendChart.destroy();
        }

        // Process data from database
        let labels = [];
        let profitData = [];
        
        if (data && data.length > 0) {
            // Assuming data comes as array of {period, sales, expenses}
            labels = data.map(item => item.period || '');
            profitData = data.map(item => {
                const sales = parseFloat(item.sales) || 0;
                const expenses = parseFloat(item.expenses) || 0;
                return sales - expenses;
            });
        } else {
            console.warn('Using fallback data for business trend chart');
            labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
            profitData = [3200, 4500, 6000, 5200];
        }

        businessTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Profit (Sales - Expenses)',
                    data: profitData,
                    borderColor: '#48BB78',
                    backgroundColor: 'rgba(72, 187, 120, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Profit: LKR ${context.raw.toFixed(2)}`;
                            },
                            afterLabel: function(context) {
                                if (data && data[context.dataIndex]) {
                                    const item = data[context.dataIndex];
                                    return `Sales: LKR ${parseFloat(item.sales || 0).toFixed(2)}\nExpenses: LKR ${parseFloat(item.expenses || 0).toFixed(2)}`;
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return 'LKR ' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    }

    // Fetch data from API
    async function fetchChartData(url, params) {
        try {
            const queryParams = new URLSearchParams(params).toString();
            const response = await fetch(`${url}?${queryParams}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching chart data:', error);
            return null;
        }
    }

    // Update weekly performance chart
    async function updateWeeklyPerformanceChart(range) {
        try {
            const chartData = await fetchChartData('http://127.0.0.1:8000/get_chart_data.php', { range });
            
            if (chartData?.weeklyPerformanceData) {
                renderWeeklyPerformanceChart(chartData.weeklyPerformanceData, range);
            } else {
                console.warn('Using fallback data for weekly performance chart');
                renderWeeklyPerformanceChart(null, range);
            }
        } catch (error) {
            console.error('Error loading weekly performance data:', error);
            renderWeeklyPerformanceChart(null, range);
        }
    }

    // Update business trend chart with proper data handling
    async function updateBusinessTrendChart(range) {
        try {
            const chartData = await fetchChartData('http://127.0.0.1:8000/get_chart_data.php', { 
                trend_range: range,
                calculate_profit: true
            });
            
            if (chartData?.businessTrendData) {
                renderBusinessTrendChart(chartData.businessTrendData, range);
            } else {
                console.warn('Using fallback data for business trend chart');
                renderBusinessTrendChart(null, range);
            }
        } catch (error) {
            console.error('Error loading business trend data:', error);
            renderBusinessTrendChart(null, range);
        }
    }

    // Fetch and render order history
    async function fetchOrderHistory() {
        const tbody = document.getElementById('order-history-body');
        if (!tbody) {
            console.error('Order history table body not found');
            return;
        }

        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4">Loading order history...</td></tr>`;
        
        try {
            const response = await fetch('http://127.0.0.1:8000/get_orders.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            renderOrderHistory(data);
        } catch (error) {
            console.error('Error fetching order history:', error);
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-red-500">Error loading order history.</td></tr>`;
        }
    }

    // Render order history table
    function renderOrderHistory(orders) {
        const tbody = document.getElementById('order-history-body');
        tbody.innerHTML = '';
        
        if (orders?.length > 0) {
            orders.forEach(order => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${order.order_id || 'N/A'}</td>
                    <td>${order.order_date ? new Date(order.order_date).toLocaleDateString() : 'N/A'}</td>
         <td>LKR ${order.total ? parseFloat(order.total).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'}</td>

                    <td>${order.payment_method}</td>
                `;
                
                row.addEventListener('click', () => loadOrderOverview(order.order_id));
                row.classList.add('cursor-pointer', 'hover:bg-gray-100');
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4">No orders found.</td></tr>`;
        }
    }

    // Load and display order details
    async function loadOrderOverview(orderId) {
        const modal = document.getElementById('order-overview-modal');
        const detailsDiv = document.getElementById('order-details');
        
        if (!modal || !detailsDiv) {
            console.error('Order overview modal or details div not found');
            return;
        }

        detailsDiv.innerHTML = '<p>Loading order details...</p>';
        modal.classList.remove('hidden');
        
        try {
            const response = await fetch(`http://127.0.0.1:8000/get_order_details.php?order_id=${orderId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            renderOrderOverview(data);
        } catch (error) {
            console.error('Error fetching order details:', error);
            detailsDiv.innerHTML = '<p class="text-red-500">Error loading order details.</p>';
        }
    }

    // Render order overview modal content
    function renderOrderOverview(order) {
        const detailsDiv = document.getElementById('order-details');
        
        if (!order) {
            detailsDiv.innerHTML = '<p class="text-red-500">No order data available.</p>';
            return;
        }

        detailsDiv.innerHTML = `
            <div class="space-y-4">
                <div class="border-b pb-2">
                    <h4 class="text-lg font-semibold text-gray-700">Order ID: ${order.order_id || 'N/A'}</h4>
                    <p class="text-gray-600">Date: ${order.order_date ? new Date(order.order_date).toLocaleString() : 'N/A'}</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-600">Subtotal: LKR ${order.subtotal ? parseFloat(order.subtotal).toFixed(2) : '0.00'}</p>
                        <p class="text-gray-600">Discount: LKR ${order.discount ? parseFloat(order.discount).toFixed(2) : '0.00'}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold text-gray-700">Total: LKR ${order.total ? parseFloat(order.total).toFixed(2) : '0.00'}</p>
                        <p class="text-gray-600">User ID: ${order.user_id || 'N/A'}</p>
                    </div>
                </div>
                
                ${order.items?.length > 0 ? `
                    <div>
                        <h5 class="text-md font-semibold text-gray-700 mb-2">Order Items:</h5>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${order.items.map(item => `
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap">${item.product_id || 'N/A'}</td>
                                            <td class="px-4 py-2 whitespace-nowrap">${item.quantity || 0}</td>
                                            <td class="px-4 py-2 whitespace-nowrap">LKR ${item.price ? parseFloat(item.price).toFixed(2) : '0.00'}</td>
                                            <td class="px-4 py-2 whitespace-nowrap">LKR ${item.quantity && item.price ? parseFloat(item.quantity * item.price).toFixed(2) : '0.00'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : '<p class="text-gray-500">No items in this order.</p>'}
            </div>
        `;
    }

    // Initialize dropdown toggle functionality
    function initDropdowns() {
        document.querySelectorAll('[data-dropdown-toggle]').forEach(button => {
            const dropdownId = button.getAttribute('data-dropdown-toggle');
            const dropdown = document.getElementById(dropdownId);
            
            if (dropdown) {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdown.classList.toggle('hidden');
                });
            }
        });

        // Close dropdowns when clicking outside
        window.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
        });
    }

    // Initialize date range selectors
    function initDateRangeSelectors() {
        // Weekly performance range selector
        document.querySelectorAll('#weekly-performance-dropdown a').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                currentWeeklyRange = this.getAttribute('data-range');
                updateWeeklyPerformanceChart(currentWeeklyRange);
                document.getElementById('weekly-performance-dropdown').classList.add('hidden');
                document.querySelector('#weekly-performance-dropdown').previousElementSibling.textContent = getFriendlyRangeText(currentWeeklyRange);
            });
        });

        // Business trend range selector
        document.querySelectorAll('#business-trend-dropdown a').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                currentBusinessRange = this.getAttribute('data-range');
                updateBusinessTrendChart(currentBusinessRange);
                document.getElementById('business-trend-dropdown').classList.add('hidden');
                document.querySelector('#business-trend-dropdown').previousElementSibling.textContent = getFriendlyRangeText(currentBusinessRange);
            });
        });
    }

    // Initialize modal functionality
    function initModal() {
        const closeModalButton = document.getElementById('close-modal-button');
        const orderOverviewModal = document.getElementById('order-overview-modal');
        
        if (closeModalButton && orderOverviewModal) {
            closeModalButton.addEventListener('click', () => {
                orderOverviewModal.classList.add('hidden');
            });

            window.addEventListener('click', (e) => {
                if (e.target === orderOverviewModal) {
                    orderOverviewModal.classList.add('hidden');
                }
            });
        }
    }

    // Initialize all components
    function init() {
        initDropdowns();
        initDateRangeSelectors();
        initModal();
        
        // Initial data load
        updateWeeklyPerformanceChart(currentWeeklyRange);
        updateBusinessTrendChart(currentBusinessRange);
        fetchOrderHistory();
    }

    // Start the application
    init();
});