/**
 * ClassFlow Pro Calendar JavaScript
 */
(function($) {
    'use strict';

    const ClassFlowCalendar = {
        calendar: null,
        currentFilters: {},
        
        init: function() {
            this.initCalendar();
            this.bindEvents();
        },

        initCalendar: function() {
            const calendarEl = document.getElementById('classflow-calendar');
            if (!calendarEl) return;

            this.calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: this.getInitialView(),
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                height: 'auto',
                events: this.fetchEvents.bind(this),
                eventClick: this.handleEventClick.bind(this),
                eventDidMount: this.handleEventMount.bind(this),
                loading: this.handleLoading.bind(this),
                buttonText: classflowCalendar.i18n,
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                slotDuration: '00:30:00',
                allDaySlot: false,
                weekNumbers: true,
                dayMaxEvents: true,
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short'
                }
            });

            this.calendar.render();
        },

        getInitialView: function() {
            const viewMap = {
                'month': 'dayGridMonth',
                'week': 'timeGridWeek',
                'day': 'timeGridDay',
                'list': 'listWeek'
            };
            return viewMap[classflowCalendar.view] || 'dayGridMonth';
        },

        bindEvents: function() {
            // Filter changes
            $('.classflow-filter').on('change', this.handleFilterChange.bind(this));
            
            // Modal close
            $('.classflow-modal-close').on('click', function() {
                $('#classflow-event-modal').hide();
            });
            
            // Click outside modal to close
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('classflow-modal')) {
                    $('#classflow-event-modal').hide();
                }
            });
        },

        fetchEvents: function(info, successCallback, failureCallback) {
            const params = {
                start_date: info.startStr,
                end_date: info.endStr,
                ...this.currentFilters
            };

            $.ajax({
                url: classflowCalendar.apiUrl,
                type: 'GET',
                data: params,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', classflowCalendar.nonce);
                },
                success: function(response) {
                    const events = response.schedules.map(schedule => ({
                        id: schedule.id,
                        title: schedule.class.name,
                        start: schedule.start_time,
                        end: schedule.end_time,
                        extendedProps: {
                            schedule: schedule,
                            instructor: schedule.instructor,
                            location: schedule.location,
                            availableSpots: schedule.available_spots,
                            isFull: schedule.is_full
                        },
                        color: schedule.is_full ? '#dc3545' : '#2271b1',
                        textColor: '#fff'
                    }));
                    successCallback(events);
                },
                error: function() {
                    failureCallback();
                }
            });
        },

        handleFilterChange: function() {
            this.currentFilters = {
                category_id: $('#classflow-filter-category').val(),
                instructor_id: $('#classflow-filter-instructor').val(),
                location_id: $('#classflow-filter-location').val()
            };
            
            // Remove empty filters
            Object.keys(this.currentFilters).forEach(key => {
                if (!this.currentFilters[key]) {
                    delete this.currentFilters[key];
                }
            });
            
            // Refetch events
            this.calendar.refetchEvents();
        },

        handleEventClick: function(info) {
            const event = info.event;
            const props = event.extendedProps;
            const schedule = props.schedule;
            
            let detailsHtml = `
                <h3>${event.title}</h3>
                <div class="classflow-event-meta">
                    <p><strong>${classflowCalendar.i18n.date}:</strong> ${moment(event.start).format('MMMM D, YYYY')}</p>
                    <p><strong>${classflowCalendar.i18n.time}:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
                    ${props.instructor ? `<p><strong>${classflowCalendar.i18n.instructor}:</strong> ${props.instructor.name}</p>` : ''}
                    ${props.location ? `<p><strong>${classflowCalendar.i18n.location}:</strong> ${props.location.name}</p>` : ''}
                    <p><strong>${classflowCalendar.i18n.availability}:</strong> 
                        <span class="${props.isFull ? 'text-danger' : 'text-success'}">
                            ${props.isFull ? classflowCalendar.i18n.fullyBooked : props.availableSpots + ' ' + classflowCalendar.i18n.spotsAvailable}
                        </span>
                    </p>
                </div>
            `;
            
            if (!props.isFull && classflowProFrontend && classflowProFrontend.isLoggedIn) {
                detailsHtml += `
                    <div class="classflow-event-actions">
                        <button class="classflow-btn classflow-btn-primary classflow-book-schedule" data-schedule-id="${schedule.id}">
                            ${classflowCalendar.i18n.bookNow}
                        </button>
                    </div>
                `;
            } else if (!classflowProFrontend || !classflowProFrontend.isLoggedIn) {
                detailsHtml += `
                    <div class="classflow-event-actions">
                        <a href="${window.location.href}?login=1" class="classflow-btn classflow-btn-primary">
                            ${classflowCalendar.i18n.loginToBook}
                        </a>
                    </div>
                `;
            }
            
            $('#classflow-event-details').html(detailsHtml);
            $('#classflow-event-modal').show();
            
            // Bind book button
            $('.classflow-book-schedule').on('click', function() {
                const scheduleId = $(this).data('schedule-id');
                ClassFlowCalendar.bookSchedule(scheduleId);
            });
        },

        handleEventMount: function(info) {
            const event = info.event;
            const props = event.extendedProps;
            
            // Add custom classes
            if (props.isFull) {
                info.el.classList.add('classflow-event-full');
            }
            
            // Add tooltip
            $(info.el).tooltip({
                title: `${event.title}\n${props.availableSpots} spots available`,
                placement: 'top',
                container: 'body'
            });
        },

        handleLoading: function(isLoading) {
            if (isLoading) {
                $('#classflow-calendar').addClass('classflow-calendar-loading');
            } else {
                $('#classflow-calendar').removeClass('classflow-calendar-loading');
            }
        },

        bookSchedule: function(scheduleId) {
            // Close modal
            $('#classflow-event-modal').hide();
            
            // Redirect to booking page or open booking modal
            if (window.ClassFlowFrontend) {
                window.ClassFlowFrontend.showBookingForm(scheduleId);
            } else {
                window.location.href = `/booking/new?schedule_id=${scheduleId}`;
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ClassFlowCalendar.init();
    });

})(jQuery);