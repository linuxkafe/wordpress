document.addEventListener('DOMContentLoaded', () => {
    let idleTimer;
    let isAutoScrolling = false; // Flag para saber se somos nós a mexer
    let animationFrameId;
    
    // Configurações para teste
    const idleTimeBeforeScroll = 3000; // 3 segundos para testar rápido
    const scrollSpeed = 1; 

    function startAutoScroll() {
        // Se a página não tiver para onde descer, aborta
        if (document.body.scrollHeight <= window.innerHeight) {
            console.log('Conteúdo cabe todo no ecrã. Nada para fazer scroll.');
            return;
        }

        if (isAutoScrolling) return;
        
        console.log('Inatividade detetada. A iniciar Auto Scroll...');
        isAutoScrolling = true;
        loopScroll();
    }

    function loopScroll() {
        if (!isAutoScrolling) return;

        window.scrollBy(0, scrollSpeed);

        // Se chegou ao fundo
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 2) {
            console.log('Fundo atingido. A voltar ao topo.');
            window.scrollTo(0, 0);
        }

        animationFrameId = requestAnimationFrame(loopScroll);
    }

    function stopAutoScroll() {
        if (isAutoScrolling) {
            console.log('Interação detetada. A parar scroll.');
            isAutoScrolling = false;
            cancelAnimationFrame(animationFrameId);
        }
    }

    function resetIdleTimer(e) {
        stopAutoScroll();
        clearTimeout(idleTimer);
        idleTimer = setTimeout(startAutoScroll, idleTimeBeforeScroll);
    }

    // REMOVIDO: 'scroll' da lista para evitar conflito com o próprio script
    const userEvents = ['mousedown', 'mousemove', 'keypress', 'touchstart', 'wheel'];
    
    userEvents.forEach(event => {
        // passive: true melhora a performance do scroll no mobile
        document.addEventListener(event, resetIdleTimer, { passive: true });
    });

    console.log('Script carregado. À espera de ' + (idleTimeBeforeScroll/1000) + 's de inatividade.');
    resetIdleTimer();
});
