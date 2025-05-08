function toggleHistorico(tarefaId) {
    const historicoRow = document.getElementById('historico-' + tarefaId);
    if (historicoRow.style.display === 'none' || historicoRow.style.display === '') {
        historicoRow.style.display = 'table-row';
    } else {
        historicoRow.style.display = 'none';
    }
}

function toggleComentarios(tarefaId) {
    const comentariosRow = document.getElementById('comentarios-' + tarefaId);
    if (comentariosRow.style.display === 'none' || comentariosRow.style.display === '') {
        comentariosRow.style.display = 'table-row';
    } else {
        comentariosRow.style.display = 'none';
    }
}

