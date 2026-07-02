/**
 * Event bridge for session view payload fetch/render handoff.
 *
 * Keeps GameShell orchestration thin by isolating the request-token/context
 * forwarding contract used by ChatPanel session views.
 */
export class SessionViewBridge {
  constructor(bus, fetchSessionViewData) {
    this.bus = bus;
    this.fetchSessionViewData = fetchSessionViewData;
    this._off = null;
  }

  register() {
    if (this._off || !this.bus || typeof this.bus.on !== 'function') {
      return;
    }
    this._off = this.bus.on('user:session-view-requested', ({ view, options, requestToken, context } = {}) => {
      if (!view || typeof this.fetchSessionViewData !== 'function') {
        return;
      }
      const requestOptions = {
        ...(options ?? {}),
        ...(context ? { context } : {}),
      };
      const normalizedRequestToken = String(requestToken || requestOptions.requestToken || '').trim();
      void this.fetchSessionViewData(view, requestOptions).then((data) => {
        this.bus.emit('session:view-data', {
          view,
          data,
          requestToken: normalizedRequestToken,
          context: requestOptions.context || null,
        });
      }).catch((err) => {
        console.error(`fetchSessionViewData(${view}) failed:`, err?.message);
      });
    });
  }

  destroy() {
    if (typeof this._off === 'function') {
      this._off();
    }
    this._off = null;
  }
}
