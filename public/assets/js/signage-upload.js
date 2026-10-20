/** XHR exposes real upload progress, including multipart transfer overhead. */
export function sendContentUpload(url, {body, token, signal, onProgress}) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const abort = () => xhr.abort();
    const finish = (error, result) => {
      signal.removeEventListener('abort', abort);
      if (error) reject(error); else resolve(result);
    };
    if (signal.aborted) { reject(new DOMException('Upload cancelled.', 'AbortError')); return; }
    xhr.open('POST', url);
    xhr.withCredentials = true;
    xhr.timeout = 40 * 60 * 1000;
    xhr.setRequestHeader('X-Requested-With', 'fetch');
    xhr.setRequestHeader('X-CSRF-Token', token);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = event => onProgress?.(event.lengthComputable ? event.loaded / event.total : null);
    xhr.upload.onload = () => onProgress?.(1);
    xhr.onload = () => {
      let result;
      try { result = JSON.parse(xhr.responseText); } catch { result = null; }
      let message;
      if (xhr.status === 401 || (xhr.responseURL && new URL(xhr.responseURL).pathname === '/login')) message = 'Your session expired. Sign in in another tab, then try again.';
      else if (xhr.status === 413) message = 'The server rejected this file because it exceeds its upload limit.';
      else if (!result) message = 'The upload could not finish. Check your connection and the server upload limit.';
      else if (xhr.status < 200 || xhr.status >= 300) message = result.error || 'Could not add this content.';
      else if (!Number.isInteger(result.id) || result.id < 1) message = 'The server did not confirm this upload. Check the content library before retrying.';
      if (message) {
        const error = new Error(message);
        error.status = xhr.status; error.fields = result?.errors ?? {};
        finish(error);
      } else finish(null, result);
    };
    xhr.onerror = () => finish(new Error('Connection lost. Check the content library before retrying this file.'));
    xhr.ontimeout = () => finish(new Error('The upload took too long. Check the content library before retrying.'));
    xhr.onabort = () => finish(new DOMException('Upload cancelled.', 'AbortError'));
    signal.addEventListener('abort', abort, {once: true});
    try { xhr.send(body); } catch (error) { finish(error); }
  });
}
