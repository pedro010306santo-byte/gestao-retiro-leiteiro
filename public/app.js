document.querySelectorAll(".abas button").forEach(botao => {
  botao.addEventListener("click", () => {
    document.querySelectorAll(".abas button, .formulario").forEach(item => item.classList.remove("ativo"));
    botao.classList.add("ativo");
    document.querySelector(`#${botao.dataset.alvo}`).classList.add("ativo");
  });
});

document.querySelectorAll(".alerta").forEach(alerta => {
  setTimeout(() => alerta.remove(), 5000);
});
