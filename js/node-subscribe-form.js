import 'babel-polyfill';
import React, {Component} from "react";
import ReactDOM from "react-dom";
import "whatwg-fetch"; // https://www.npmjs.com/package/whatwg-fetch
import renderHTML from "react-render-html";
import ReCAPTCHA from "react-google-recaptcha";
import * as Cookies from "js-cookie";
import ReactPlaceholder from "react-placeholder";
import "react-placeholder/lib/reactPlaceholder.css";
import ReactTooltip from "react-tooltip";
import '../css/subscribe-block.css';
const queryString = require("query-string");
let cookies = require("cookie-getter");

const api = {
  initialize: "/node_subscribe/init",
  my_subscriptions: "/node_subscribe/my_subscriptions",
  subscription_status: "/node_subscribe/subscription_status",
  subscribe: "/node_subscribe/subscribe",
  unsubscribe: "/node_subscribe/unsubscribe",
  unsubscribe_from_email: "/node_subscribe/unsubscribeFromEmail",
  verification: "/node_subscribe/verification",
  account_delete: "/node_subscribe/account_delete",
  account_suspend: "/node_subscribe/account_suspend",
};

class NodeSubscribeForm extends Component {

  constructor(props) {
    super(props);

    this.state = {
      app_config: {
        config_loaded: false,
        captcha: {},
        modal_messages: {},
        theme: {},
        subscribe_bar_text: {},
        subscribe_button_text: {},
      },
      loading: true,
      error_modal: false,
      message_modal: false,
      manage_modal: queryString.parse(location.search).manage_subscriptions || false,
      verification: queryString.parse(location.search).subscriber || false,
      unsubscribe: queryString.parse(location.search).unsubscribe || false,
      email: queryString.parse(location.search).email || "",
      subscription_status: "",
      domain: window.location.hostname,
      alias: window.location.pathname,
      token: cookies("Drupal.visitor.subscription_token") || null,
      data: {},
      captcha_value: "",
      show_captcha: false,
    };

    this.init = this.init.bind(this);
    this.isSubscribed = this.isSubscribed.bind(this);
    this.subscribe = this.subscribe.bind(this);
    this.unsubscribe = this.unsubscribe.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
    this.toggleModal = this.toggleModal.bind(this);
    this.saveCookie = this.saveCookie.bind(this);
    this.doNotSaveCookie = this.doNotSaveCookie.bind(this);
    this.removeSubscriptionStatus = this.removeSubscriptionStatus.bind(this);
    this.getFunctions = this.getFunctions.bind(this);
    this.forget = this.forget.bind(this);
    this.toggleManage = this.toggleManage.bind(this);
    this.logout = this.logout.bind(this);
    this.onCaptchaChange = this.onCaptchaChange.bind(this);
    this.new_subscriber_email_sent_message = this.new_subscriber_email_sent_message.bind(this);
    this.closeMessageModal = this.closeMessageModal.bind(this);
    this.afterUnsubscribe = this.afterUnsubscribe.bind(this);
    this.scrollToSubscribeBanner = this.scrollToSubscribeBanner.bind(this);
    this.unsubscribeFromEmail = this.unsubscribeFromEmail.bind(this);
    this.removeURLVerificationToken = this.removeURLVerificationToken.bind(this);
    this.removeURLUnsubscribeQuery = this.removeURLUnsubscribeQuery.bind(this);
    this.accountDelete = this.accountDelete.bind(this);
  }

  init(){
    let app_init = {};
    let self = this;
    /*
     * fetch init api to get initial app state
     */
    fetch(api.initialize + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "GET", headers: { "Content-Type": "application/json" },
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          app_init = data;

          /*
           * Prepare initial app state
           */
          const subscription_pending_message = {
            title: app_init.modal_messages.subscription_pending_message.title,
            body: app_init.modal_messages.subscription_pending_message.body,
            help_text: app_init.modal_messages.subscription_pending_message.help_text,
          };
          const subscription_pending_actions = [
            {
              type: "btn-button",
              label: app_init.modal_messages.subscription_pending_message.button,
              callbacks: [],
            }, {
              type: "btn-link",
              label: self.state.email ? `Not ${self.state.email}? Use a different email.` : "Use a different email.",
              callbacks: [
                self.getFunctions("do_not_save"),
                self.getFunctions("remove_subscription_status"),
                self.getFunctions("forget"),
              ],
            }
          ];
          const account_delete_message = {
            title: app_init.account_delete_message.title || "Are you sure?",
            body: app_init.account_delete_message.body || "Are you sure you want to delete your account and it's followed pages?",
            help_text: app_init.account_delete_message.help_text || "",
          };
          const account_delete_actions = [
            {
              type: "btn-button",
              label: app_init.account_delete_message.confirm_button || "Yes",
              callbacks: [
                self.getFunctions("account_delete"),
                self.getFunctions("close"),
                self.getFunctions("toggle_manage"),
              ],
            },
            {
              type: "btn-button",
              label: app_init.account_delete_message.cancel_button || "No",
              callbacks: ["close"],
            },
          ];
          const email_validation_error_message = {
            title: app_init.modal_messages.email_validation_error.title,
            body: app_init.modal_messages.email_validation_error.body,
            help_text: app_init.modal_messages.email_validation_error.help_text,
          };
          const email_validation_error_actions = [
            {
              type: "btn-button",
              label: app_init.modal_messages.email_validation_error.button,
              callbacks: [],
            }
          ];
          const new_subscriber_email_sent_message = {
            title: app_init.modal_messages.new_subscriber_email_sent.title,
            body: app_init.modal_messages.new_subscriber_email_sent.body
          };
          const new_subscriber_email_sent_actions = [
            {
              type: "btn-button",
              label: app_init.modal_messages.new_subscriber_email_sent.button,
              callbacks: [],
            }
          ];
          const manage_modal = {
            title: app_init.modal_messages.manage_modal.title,
            body: app_init.modal_messages.manage_modal.body,
            help_text: app_init.modal_messages.manage_modal.help_text,
            unsubscribe_button: app_init.modal_messages.manage_modal.unsubscribe_button,
          };
          const manage_modal_actions = [
            {
              type: "btn-button",
              label: app_init.modal_messages.manage_modal.button,
              callbacks: [],
            }
          ];

          /*
           * Set initial app state by mapping fetched values from API
           */
          self.setState({
            loading: false,
            app_config: {
              config_loaded: true,
              development_mode: app_init.development_mode,
              privacy_url: app_init.privacy_url,
              captcha: {
                captcha_required: app_init.captcha.captcha_required,
                captcha_key: app_init.captcha.captcha_key,
                captcha_theme: app_init.captcha.captcha_theme,
              },
              modal_messages: {
                subscription_pending_message: {
                  message: {...subscription_pending_message},
                  actions: subscription_pending_actions,
                },
                email_validation_error: {
                  message: {...email_validation_error_message},
                  actions: email_validation_error_actions,
                },
                new_subscriber_email_sent: {
                  message: {...new_subscriber_email_sent_message},
                  actions: new_subscriber_email_sent_actions,
                },
                manage_modal: {
                  message: {...manage_modal},
                  actions: manage_modal_actions,
                },
                account_delete: {
                  message: {...account_delete_message},
                  actions: account_delete_actions,
                }
              },
              theme:{
                icon_link: app_init.theme.icon_link || null,

              },
              subscribe_bar_text: {
                subscription_prompt_title: app_init.subscribe_bar_text.subscription_prompt_title,
                subscribe_prompt: app_init.subscribe_bar_text.subscribe_prompt,
                subscribed_title: app_init.subscribe_bar_text.subscribed_title,
                subscribed_now_title: app_init.subscribe_bar_text.subscribed_now_title,
                unsubscribed_now_title: app_init.subscribe_bar_text.unsubscribed_now_title,
                subscribed_message: app_init.subscribe_bar_text.subscribed_message,
                pending_message: app_init.subscribe_bar_text.pending_message,
                unsubscribe_message: app_init.subscribe_bar_text.unsubscribe_message,
                email_box_placeholder: app_init.subscribe_bar_text.email_box_placeholder
              },
              subscribe_button_text: {
                subscribe: app_init.subscribe_button_text.subscribe,
                cancel_subscription: app_init.subscribe_button_text.cancel_subscription,
                subscription_pending: app_init.subscribe_button_text.subscription_pending,
                manage_subscription: app_init.subscribe_button_text.manage_subscription,
              },
              account_manage_button_label_text: {
                suspend: app_init.account_manage_button_label_text.suspend,
                unsuspend: app_init.account_manage_button_label_text.unsuspend,
                delete: app_init.account_manage_button_label_text.delete,
              },
              account_manage_button_text: {
                suspend: app_init.account_manage_button_text.suspend,
                unsuspend: app_init.account_manage_button_text.unsuspend,
                delete: app_init.account_manage_button_text.delete,
              },
            }
          });

          //Initial actions
          if(this.state.verification && !this.state.unsubscribe){
            /* State will have a url verification token if user clicked an email link
             * to verify the sign up or device. And must not have the unsubscribe
             * parameter to true. Otherwise, it should be unsubscribing from email.
             * fetches verification api.
             */
            this.verification();
          }else if(this.state.unsubscribe){
            /* State with unsubscribe set means a user click on an email link
             * to unsubscribe from the email.
             * fetches unsubscribeFromEmail api
             */
            this.unsubscribeFromEmail();
          }else{
            /* App will at least fire this isSubscribed API to get data about the
             * current user, checks subscription status of the current page they"re on.
             * fetches subscription status.
             */
            this.isSubscribed();
          }

        });
      } else {
        console.log("error getting app init data");
      }
    });

  }

  isSubscribed(){
    let self = this;
    let {app_config: {development_mode}} = this.state;

    fetch(api.subscription_status + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include",
      headers: { "Content-Type": "application/json"},
      body: JSON.stringify({
            "alias": this.state.alias,
            "token": this.state.token,
          }),
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          this.setState({
            data: data,
          });
          development_mode && console.log("got isSubscribed response", data);
          if(data && data.status) {
              self.setState({
                subscription_status: data.status,
                user_status: data.user_status,
                email: data.email,
              });
          }else{
            development_mode && console.log("user has never subscribed", data.email);
            self.setState({
              subscription_status: "",
              user_status: "",
              //email: data.email,
            });
          }
          if(data && data.message){
            self.setState(
                {
                  message_modal: {
                    message: data.message,
                    actions: data.actions,
                  },
                }
            )
          }

          //remove token from cookie if user is set for deletion,
          //this should happen to users devices with a verified token only
          //so when one of those devices calls this api, it will LOGOUT the user
          //if the user status is marked for as delete
          if(data && data.user_status === "delete"){
            development_mode && console.log("user has been deleted", data);
            self.logout();
          }
        });
      } else {
        console.log("error getting subscription data");
      }
    });
  }

  verification(){
    let self = this;
    let url = api.verification;
    let body = {"subscriber": this.state.verification};

    fetch(url + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include",
      headers: { "Content-Type": "application/json"},
      body: JSON.stringify(body),
    }).then((response) => {
      /**
       * response logic
       * expected response object:
       * { verified: boolean, subscription_token: token, message: object }
       */
      if(response.ok){
        response.json().then((data) => {
          if(data.error){
            if(data.message){
              self.setState({
                message_modal: {
                  message: data.message,
                  actions: data.actions,
                },
              });
            }else{
              self.setState({error_modal: "Something went wrong!"});
            }
          }else{
            if(data.verified){
              self.setState({
                verification: "",
                message_modal: {
                  message: data.message,
                  actions: data.actions,
                }
              });
            }else{
              self.setState({error_modal: "Verification failed."});
            }
          }
        });

        //remove token from url if there is one, this happens after a user
        //clicked on a verification link in their email, prevent copying
        //and refreshing to the same link again.
        this.removeURLVerificationToken();
      }
    });

  }

  subscribe(){
    let self = this;
    let url = api.subscribe;
    let {alias, token, email, captcha_value, app_config: {development_mode}} = this.state;
    let body = {"alias": alias, "token": token, "captcha_value": captcha_value};

    if(!this.state.token){
      body = {"alias": alias, "email": email, "captcha_value": captcha_value};
    }

    this.setState({
      //todo: add check config - recaptcha_required?
      //when it gets to call this function, it means captcha passes,
      //turn off show_captcha before continuing.
      show_captcha: false,
    });

    fetch(url + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    }).then((response) => {
      //response logic
      if(response.ok){
        response.json().then((data) => {
          development_mode && console.log("Subscribe response", data);
          if(data.error){ //if response contains error
            if(data.message){
              self.setState({error_modal: data.message});
            }else{
              self.setState({error_modal: "Something went wrong!"});
            }
          }else{ //response has no error
            if(data.subscribed && data.subscription_token){ //if is brand new user
              self.setState({
                token: data.subscription_token || self.state.token,
                message_modal: {
                  message: data.message,
                  actions: data.actions,
                },
                subscription_status: data.subscription_status || "Error",
              });
            }else if(data.subscribed){ //existing user
              if(data.subscription_status === "enabled"){
                self.setState({
                  subscription_status: "enabled_now", //keyword to display a subscribed message
                });
              }else{
                self.setState({
                  subscription_status: data.subscription_status || "Error",
                });
              }
            }else{
              if(data.verification_required){
                self.setState({
                  message_modal: {
                    message: data.message,
                    actions: data.actions,
                  },
                  email: data.email,
                });
              }else if(data.new_device){
                self.setState({
                  message_modal: {
                    message: data.message,
                    actions: data.actions,
                  },
                  subscription_status: data.subscription_status || "Error",
                  token: data.subscription_token || self.state.token,
                });
              }
            }
          }
        });
      }else{
        console.log("Error getting data");
      }
    });

  }

  unsubscribe(){
    let self = this;

    fetch(api.unsubscribe + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include", headers: {
        "Content-Type": "application/json"
      }, body: JSON.stringify({
            "alias": this.state.alias,
            "token": this.state.token,
            "email": this.state.email,
          }),
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          if(data.message && data.actions){
            self.setState({
              message_modal: {
                message: data.message,
                actions: data.actions,
              },
            });
            if(data.subscription_status === "disabled"){
              self.setState({
                subscription_status: "disabled_now",
              });
            }
          }else{
            self.setState({
              subscription_status: "disabled_now",
            });
          }
        });

        //Resets unsubscribe state to false once it is done unsubscribing.
        self.removeURLUnsubscribeQuery();
      } else {
        console.log("error getting data");
      }
    });
  }

  unsubscribeFromEmail(){
    let self = this;

    fetch(api.unsubscribe_from_email + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include", headers: {
        "Content-Type": "application/json"
      }, body: JSON.stringify({
        "alias": this.state.alias,
        "token": this.state.token || queryString.parse(location.search).token,
        "subscriber" : this.state.verification,
        "email": this.state.email,
      }),
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          if(data.message && data.actions){
            self.setState({
              message_modal: {
                message: data.message,
                actions: data.actions,
              },
            });
            if(data.subscription_status === "disabled"){
              self.setState({
                subscription_status: "disabled_now",
              });
            }
          }else{
            self.setState({
              subscription_status: "disabled_now",
            });
          }
        });
      } else {
        console.log("error getting data");
      }
    });
  }

  accountDelete(){
    let self = this;

    fetch(api.account_delete + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include", headers: {
        "Content-Type": "application/json"
      }, body: JSON.stringify({
        "alias": this.state.alias,
        "token": this.state.token,
        "email": this.state.email,
      }),
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          if(!data.error){
            self.isSubscribed();
          }else{
            console.log("error deleting account", data);
          }
        });
      }else{
        console.log("error getting data");
      }
    });
  }

  /*
   * Controls the modal displays
   * modal_state_name: the name used in react state
   * config: sets the content of the modal
   * action: "open" or "close"
   * callback: a single callback function, or an array of callback function or string.
   */
  toggleModal(modal_state_name, config = true, action = null, callback = null){
    let self = this;
    this.state.app_config.development_mode && console.log(action || "toggles", modal_state_name);
    switch(action){
      case "open":
        this.setState({[modal_state_name]: config});
        document.body.classList.add("fixed");
        break;
      case "close":
        this.setState({[modal_state_name]: false});
        document.body.classList.remove("fixed");
        break;
      default:
        if(this.state.modal_state_name){
          this.setState({[modal_state_name]: false});
        }else{
          this.setState({[modal_state_name]: config});
        }
    }

    if(callback){
      if(callback.length > 0){ //if it"s an array of callbacks
        callback.forEach((cb)=>{
          if(typeof cb === "string"){
            let myCb = self.getFunctions(cb);
            myCb();
          }else{
            cb();
          }
        })
      }else if(callback.length === undefined){ //if it"s a single callback
        callback();
      }
    }
  }

  /*
   * Action / Callback Functions
   */
  saveCookie(){
    this.state.app_config.development_mode && console.log("saveCookie", this.state.token);
    Cookies.set("Drupal.visitor.subscription_token", this.state.token, {domain: this.state.domain});
  }
  doNotSaveCookie(){
    this.setState({token: null, email: ""});
  }
  forget(){
    Cookies.remove("Drupal.visitor.subscription_token", {domain: this.state.domain});
  }
  removeSubscriptionStatus(){
    this.setState({subscription_status: ""});
  }
  logout(){
    this.removeURLVerificationToken();
    this.removeSubscriptionStatus();
    this.doNotSaveCookie();
    this.forget();
  }
  validateEmail(email){
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
  }
  afterValidation(){
    window.location.href = window.location.origin + window.location.pathname;
  }
  new_subscriber_email_sent_message(){
    let {app_config: {modal_messages: {new_subscriber_email_sent}}} = this.state;
    this.toggleModal("message_modal", new_subscriber_email_sent , "open");
  }
  closeMessageModal(){
    this.toggleModal("message_modal", false, "close");
  }
  afterUnsubscribe(){
    window.location.href = window.location.origin + window.location.pathname;
  }
  scrollToSubscribeBanner(){
    document.getElementById("node-subscribe-form-wrapper").scrollIntoView();
  }
  removeURLVerificationToken(){
    this.setState({verification: false});
    if(queryString.parse(window.location.search)){
      let queries = queryString.parse(window.location.search);
      if(queries.subscriber){
        let filteredQueryString = queryString.stringify(delete queries.subscriber);
        window.history.replaceState({}, document.title, window.location.origin + window.location.pathname + filteredQueryString);
      }
    }
  }
  removeURLUnsubscribeQuery(){
    this.setState({unsubscribe: false});
    if(queryString.parse(window.location.search)){
      let queries = queryString.parse(window.location.search);
      if(queries.subscriber && queries.token && queries.unsubscribe){
        let filteredQueryString = queryString.stringify(delete queries.subscriber);
        filteredQueryString = queryString.stringify(delete queries.token);
        filteredQueryString = queryString.stringify(delete queries.unsubscribe);
        window.history.replaceState({}, document.title, window.location.origin + window.location.pathname + filteredQueryString);
      }
    }
  }

  /*
   * Handles calling functions through callbacks from server
   */
  getFunctions(name){
    switch (name){
      case "logout":
        return this.logout; break;
      case "save_cookie":
        return this.saveCookie; break;
      case "forget":
        return this.forget; break;
      case "do_not_save":
        return this.doNotSaveCookie; break;
      case "remove_subscription_status":
        return this.removeSubscriptionStatus; break;
      case "after_validation":
        return this.afterValidation; break;
      case "new_subscriber_email_sent_message":
        return this.new_subscriber_email_sent_message; break;
      case "close_message_modal":
        return this.closeMessageModal; break;
      case "toggle_manage":
        return this.toggleManage; break;
      case "after_unsubscribe":
        return this.afterUnsubscribe; break;
      case "refresh_status":
        return this.isSubscribed; break;
      case "scroll_to_subscribe_banner":
        return this.scrollToSubscribeBanner; break;
      case "account_delete":
        return this.accountDelete; break;
      default:
        return ()=>{};
    }
  }

  /*
   * Handles Captcha response
   */
  onCaptchaChange(value) {
    if(value) { //call subscribe when it gets a value
      this.state.app_config.development_mode && console.log("Captcha value:", value);
      this.setState({
        captcha_value: value,
      });
      this.subscribe();
    }
  }

  /*
   * Handles the submit button, subscribe / unsubscribe
   */
  onSubmit(e){
    e.preventDefault();
    let {app_config: {captcha: {captcha_required}}} = this.state; //app config: captcha
    let {subscription_status, token, email} = this.state; //variables
    let {app_config: {modal_messages: {subscription_pending_message, email_validation_error}}} = this.state; //config

    if(this.validateEmail(email) || token){ //check client validation email
      if(subscription_status !== "enabled" && subscription_status !== "enabled_now" && subscription_status !== "pending"){

        // if app config required captcha and there is no token (new signup or new device) or
        // if app config required captcha and the user had subscribed to this page before
        if((captcha_required && !token) || (captcha_required && subscription_status)){
          this.setState({
            show_captcha: true,
          });
        }else{
          this.subscribe();
        }

      }else if(subscription_status === "pending") {
        this.toggleModal("message_modal", subscription_pending_message, "open");
      }else{
        this.unsubscribe();
      }
    }else{ //error
      this.setState({email_error: {error: "_INVALID_EMAIL"}});
      this.toggleModal("message_modal", email_validation_error, "open");
    }
  }

  /*
   * Handles manage button, opens the manage subscription modal
   */
  toggleManage(e){
    if(e) {
      e.preventDefault();
    }
    let {status, manage_modal} = this.state;
    let {app_config: {modal_messages: {subscription_pending_message, new_subscriber_email_sent, email_validation_error}}} = this.state; //config

    if(status === "pending"){
      this.toggleModal("message_modal", subscription_pending_message, "open");
    }else if(status === ""){
      this.toggleModal("message_modal", new_subscriber_email_sent, "open");
    }else if(manage_modal){
      this.setState({
        manage_modal: false
      })
    }else{
      this.setState({
        manage_modal: true
      })
    }
  }

  /*
   * React component will mount, fetches configurable settings from server
   */
  componentWillMount(){
    let self = this;
    let {loading, app_config: {config_loaded} } = this.state;
    let last_known_scroll_position = document.documentElement.scrollTop;
    let initialize_at = document.querySelector(".pre-footer").offsetTop - window.innerHeight;
    let init_called = false;

    /* window.addEventListener('scroll', function(e) { */

      /* last_known_scroll_position = window.scrollY;
      initialize_at = document.querySelector(".pre-footer").offsetTop - window.innerHeight; */

      /* if(last_known_scroll_position > initialize_at) { */
        if (loading && !config_loaded && !init_called) {
          init_called = true;
          self.init();
        }
      /* } */

    /* }); */
  }

  /*
   * React component did mount, initialize the application states
   */
  componentDidMount(){

    if(this.state.verification && !this.state.unsubscribe){
      /* State will have verification token if user clicked an email link
       * to verify the sign up or device. And must not have the unsubscribe
       * parameter to true. Otherwise, it should be unsubscribing from email.
       * fetches verification api.
       */
      console.log("running verification");
      this.verification();
    }else if(this.state.unsubscribe){
      /* State with unsubscribe set means a user click on an email link
       * to unsubscribe from the email.
       * fetches unsubscribeFromEmail api
       */
      console.log("unsubscribe from email");
      this.unsubscribeFromEmail();
    }
  }

  /*
   * NodeSubscribeForm component render
   */
  render() {
    let self = this;
    let {loading, app_config} = this.state;
    let {app_config: {captcha: {captcha_required, captcha_key, theme}, development_mode, privacy_url}, show_captcha} = this.state; //app config: captcha
    let {app_config: {theme: {icon_link}}} = this.state; //app config: theme
    let {app_config: {subscribe_bar_text: {subscribed_title, subscribed_now_title, unsubscribed_now_title, subscription_prompt_title}}} = this.state; //app config: UI titles
    let {app_config: {subscribe_bar_text: {subscribe_prompt, subscribed_message, pending_message, unsubscribe_message}}} = this.state; //app config: UI text
    let {app_config: {subscribe_bar_text: {email_box_placeholder}}} = this.state; //app config: email input placeholder
    let {app_config: {subscribe_button_text: {cancel_subscription, subscription_pending, subscribe, manage_subscription}}} = this.state; //app config: buttons
    let {subscription_status, user_status, token, email} = this.state; //variables
    let {email_error, error_modal, message_modal, manage_modal} = this.state; //modals

    development_mode && console.log("State on main render", this.state);

    let getTitle = ()=>{
      if(subscription_status === "enabled"){
        return subscribed_title;
      }else if(subscription_status === "enabled_now"){
        return subscribed_now_title;
      }else if(subscription_status === "disabled_now"){
        return unsubscribed_now_title;
      }else{
        return subscription_prompt_title;
      }
    };

    let getMessage = ()=>{
      if(token){
        if(subscription_status === "enabled" || subscription_status === "enabled_now"){
          return subscribed_message;
        }else if(subscription_status === "pending"){
          return pending_message;
        }else if(subscription_status === "disabled_now"){
          return unsubscribe_message;
        }else if(subscription_status === "disabled"){
          return subscribe_prompt;
        }else{
          return subscribe_prompt;
        }
      }else{
        return subscribe_prompt;
      }
    };

    let showEmailField = ()=>{
      return (subscription_status === "" && (token === null || token === undefined));
    };

    let getSubscribeButton = ()=>{
      development_mode && console.log("Submit button status", subscription_status);
      if(subscription_status === "enabled" || subscription_status === "enabled_now"){
        return cancel_subscription;
      }else if(subscription_status === "pending"){
        return subscription_pending;
      }else{
        return subscribe;
      }
    };

    return (
      <div id="node-subscribe-form-wrapper"
           className={(subscription_status === "enabled" || subscription_status === "enabled_now") && "subscribed"}>
        <form className={icon_link ? 'has-icon':'no-icon'}>
          {icon_link &&
          <div id="subscribe-icon">
            {/*<i/>*/}
            <ReactPlaceholder
                style={{width: 42, height: 58, marginTop: 0, borderRadius: 4}}
                className="loading-icon"
                type="rect" ready={!loading} showLoadingAnimation>
              <img alt="Subscription Icon" src={icon_link}/>
            </ReactPlaceholder>
          </div>
          }
          <div id="subscribe-message">
            <ReactPlaceholder style={{height: 30, marginTop: 0, marginBottom: 6}} className="loading-h4"
                              type="textRow" rows={1} ready={!loading} showLoadingAnimation>
                <h2 className="heading">{getTitle()}</h2>
            </ReactPlaceholder>
            <ReactPlaceholder style={{height: 22, marginTop: 4}} className="loading-text"
                              type="textRow" rows={1} ready={!loading} showLoadingAnimation>
              <p className="message">{getMessage()}</p>
            </ReactPlaceholder>
          </div>
          <div id="subscribe-action" className={`${showEmailField() && "show-email"} ${show_captcha && "show-captcha"}`}>
            <div id="subscribe-action-form">
              {(showEmailField() && !show_captcha) &&
                <input id="subscribe-email" className={`input ${email_error && "error"}`}
                       type="email" required placeholder={email_box_placeholder} aria-label="email address"
                       onChange={(e)=>{this.setState({email: e.target.value})}} />
              }
              <div id="subscribe-action-buttons">
                {(!showEmailField() && !show_captcha && subscription_status !== 'pending') &&
                  <ReactPlaceholder style={{ width: 112, height: 14, marginTop: 0, marginBottom: 8}} className="loading-link"
                                    type="rect" ready={!loading} showLoadingAnimation>
                    <button id="subscribe-manage-toggle" className="button btn-link" onClick={this.toggleManage}>
                      {manage_subscription}
                    </button>
                  </ReactPlaceholder>
                }
                {!show_captcha &&
                  <button id="subscribe-submit" className="button" disabled={loading}
                          style={loading ? { width: 112, height: 40, marginTop: 0, marginRight: 0, borderRadius: 4} : {}}
                          type="submit" onClick={this.onSubmit}>
                          {getSubscribeButton()}
                  </button>
                }
              </div>
            </div>
            {(showEmailField() && !show_captcha) &&
            <a id="subscribe-privacy-link" className="button btn-link"
               target="_blank"
               href={privacy_url}>Privacy Policy</a>
            }
            {(captcha_required && captcha_key && show_captcha) ?
            <ReCAPTCHA
                className="reCAPTCHA"
                theme={theme}
                ref="recaptcha"
                sitekey={captcha_key}
                onChange={this.onCaptchaChange}
            /> :
                null
            }
          </div>
        </form>
        {(manage_modal && !loading) &&
        <ManageModal app_config={app_config}
                     subscription_status={subscription_status}
                     user_status={user_status}
                     token={token}
                     email={email}
                     refresh_status={this.isSubscribed}
                     toggleManage={this.toggleManage}
                     toggleModal={this.toggleModal}
                     logout={this.logout}
                     accountDelete={this.accountDelete}
        />
        }
        {message_modal &&
        <div id="message-modal" className="subscription-modal">
          <h2>{message_modal.message.title || null}</h2>
          <p>{message_modal.message.body ? renderHTML(message_modal.message.body) : null}</p>
          <p className="help-text">{message_modal.message.help_text ? renderHTML(message_modal.message.help_text) : null}</p>
          {(message_modal.actions && message_modal.actions.length) && message_modal.actions.map((action_button)=>
              <button key={action_button.label} className={`btn ${action_button.type}`} onClick={()=>{
                self.toggleModal("message_modal", false, "close", action_button.callbacks)
              }}>{action_button.label}</button>
            )
          }
        </div>
        }
        {error_modal &&
        <div id="error-modal" className="subscription-modal error-modal">
          <h2>{error_modal.title || "Error!"}</h2>
          <p>{error_modal.body || "Something went wrong!"}</p>
          <button onClick={()=>{this.toggleModal("error_modal", false, "close")}} className="btn btn-button">Close</button>
        </div>
        }
      </div>
    );
  }
}

class ManageModal extends Component{

  constructor(props){
    super(props);
    this.state = {
      loading: true,
      view: {
        "manage": true,
        "settings": false,
      },
      user_status: props.user_status,
      subscriptions: [],
    };

    this.fetchSubscriptions = this.fetchSubscriptions.bind(this);
    this.unsubscribeByAlias = this.unsubscribeByAlias.bind(this);
    this.accountSuspend = this.accountSuspend.bind(this);
    this.toggleView = this.toggleView.bind(this);
  }

  unsubscribeByAlias(alias, token){
    let self = this;
    let {app_config: {development_mode}, refresh_status} = this.props;

    fetch(api.unsubscribe + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include", headers: {
        "Content-Type": "application/json"
      }, body: JSON.stringify({
        "alias": alias,
        "token": token,
      }),
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          //potentially need to have api return a confirmation
          let updated_subscriptions = self.state.subscriptions.filter(function(node){
            return node.alias !== alias;
          });
          self.setState({
            subscriptions: updated_subscriptions,
          });
          refresh_status();
        });
      } else {
        console.log("Problem fetching unsubscribe");
      }
    });
  }

  fetchSubscriptions(){
    let self = this;
    let {app_config: {development_mode}, subscription_status, token, email} = this.props;
    fetch(api.my_subscriptions + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include", headers: {
        "Content-Type": "application/json"
      }, body: JSON.stringify({
        "token": token,
      }),
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          if(data.subscriptions.length){
            self.setState({
              loading: false,
              subscriptions: data.subscriptions,
            })
          }
        });
      } else {
        console.log("error getting data");
      }
    });
  }

  accountSuspend(){
    let self = this;
    let {user_status} = this.state;
    let {app_config: {development_mode}, subscription_status, token, email} = this.props;
    fetch(api.account_suspend + '?' + new URLSearchParams({
      lang: drupalSettings.language
    }), {
      method: "POST", credentials: "include", headers: {
        "Content-Type": "application/json"
      }, body: JSON.stringify({
        "token": token,
        "suspend": user_status !== "suspended",
      }),
    }).then((response) => {
      if (response.ok) {
        response.json().then((data) => {
          if(data.user_status === "suspended"){
            self.setState({
              user_status: "suspended"
            })
          }else{
            self.setState({
              user_status: "active"
            })
          }
          console.log("got response from suspend", data);
        });
      } else {
        console.log("error getting data");
      }
    });
  }

  toggleView(view){
    let self = this;
    switch (view){
      case "manage": self.setState({view: {"manage": true, "settings": false}}); break;
      case "settings": self.setState({view: {"manage": false, "settings": true}}); break;
    }
  }

  componentDidMount(){
    this.fetchSubscriptions();
    document.body.classList.add("fixed");
  }

  componentWillUnmount(){
    document.body.classList.remove("fixed");
  }

  //ManageModal component render
  render(){
    let self = this;
    let {app_config, app_config:{development_mode, modal_messages:{account_delete}, account_manage_button_label_text, account_manage_button_text} , subscription_status, token, email} = this.props; //variables
    let {toggleManage, toggleModal, logout, accountDelete} = this.props; //functions
    let {loading, subscriptions, view} = this.state;

    console.log('app config', app_config);

    development_mode && console.log("Manage modal render state", this.state);
    development_mode && console.log("Manage modal render app_config", app_config);

    let getSubscriptionList = ()=>{
      if(subscriptions.length){
        return (
            <ul id="manage-subscription-list">
              {subscriptions.map((node) =>
                <li key={node.nid}>
                  <a target="_blank" href={node.alias}>{node.title}</a>
                  {/*<button className="btn btn-sm btn-button" onClick={()=>{self.unsubscribeByAlias(node.alias, token)}}>*/}
                    {/*{node.status === "enabled" && (app_config.modal_messages.manage_modal.message.unsubscribe_button || "Unsubscribe")}*/}
                  {/*</button>*/}
                  <span role="button" aria-label="Unfollow" className="btn-unsubscribe"
                        onClick={()=>{self.unsubscribeByAlias(node.alias, token)}}
                        data-for="managePagesUnsubscribe"
                        data-tip={node.status === "enabled" && (app_config.modal_messages.manage_modal.message.unsubscribe_button || "Unsubscribe")}>
                        <i className="icon-circle-minus"/>
                  </span>
                  <ReactTooltip class="tooltips" place="top" type="dark" effect="solid" id='managePagesUnsubscribe'/>
                </li>
              )}
            </ul>
        );
      }else{
        return (
          <div id="manage-subscription-list-empty">
            <p>
              {app_config.modal_messages.manage_modal.message.body &&
              renderHTML(app_config.modal_messages.manage_modal.message.body)}
            </p>
            <p className="help-text">
              {app_config.modal_messages.manage_modal.message.help_text &&
              renderHTML(app_config.modal_messages.manage_modal.message.help_text)}
            </p>
          </div>
        );
      }
    };

    let getSettingsView = ()=>{
      let {user_status} = this.state;
      return(
          <div>
            <ul id="manage-subscription-settings">
              <li>
                <span className="separator-title">{email ? `Settings for ${email}` : "Settings"}</span>
              </li>
              <li>
                {/*todo: use text from init config api*/}
                <span>{ user_status === "suspended" ? account_manage_button_label_text.unsuspend : account_manage_button_label_text.suspend }</span>
                <button className="btn btn-sm btn-button" onClick={this.accountSuspend}>{user_status === "suspended" ? account_manage_button_text.unsuspend : account_manage_button_text.suspend}</button>
              </li>
              <li>
                <span>{account_manage_button_label_text.delete}</span>
                <button className="btn btn-sm btn-button" onClick={()=>{
                  toggleModal("message_modal", account_delete, "open")
                }}>{account_manage_button_text.delete}</button>
              </li>
            </ul>
          </div>
      )
    };

    return(
        <div id="manage-modal" className="subscription-modal">
          <h3>{app_config.modal_messages.manage_modal.message.title || "Manage Updates" }</h3>
          {/*<hr/>*/}
            {view.manage &&
              <div>
                {getSubscriptionList()}
                {/*<button onClick={(e)=>{toggleManage(e)}} className="btn btn-button">{app_config.modal_messages.manage_modal.actions[0].label || "OK"}</button>*/}
                <button onClick={(e)=>{logout(); toggleManage(e)}} className="btn btn-link">{email ? `Not ${email}? Use a different email.` : "Use a different email."}</button>
              </div>
            }
            {view.settings && getSettingsView()}
          <div id="manage-modal-tabs">
            <ul className="manage-modal-tabs-list">
              <li role="button" tabIndex="0" aria-label="Manage" data-for="managePagesList" data-tip="Page List"
                  className={`manage-modal-tabs-list-item ${view.manage && "active"}`}
                  onClick={()=>{return self.toggleView("manage")}}>
                <i className="icon-bullet-list"/>
              </li>
              <li role="button" tabIndex="1" aria-label="Settings" data-for="managePagesSettings" data-tip="Settings"
                  className={`manage-modal-tabs-list-item ${view.settings && "active"}`}
                  onClick={()=>{return self.toggleView("settings")}}>
                <i className="icon-gear"/>
              </li>
              <li role="button" tabIndex="2" aria-label="Close" data-for="managePagesClose" data-tip="Close"
                  className={`manage-modal-tabs-list-item`}
                  onClick={(e)=>{toggleManage(e)}}>
                <i className="icon-close"/>
              </li>
            </ul>
            <ReactTooltip class="tooltips" place="top" type="dark" effect="solid" id='managePagesList'/>
            <ReactTooltip class="tooltips" place="top" type="dark" effect="solid" id='managePagesSettings'/>
            <ReactTooltip class="tooltips" place="top" type="dark" effect="solid" id='managePagesClose'/>
          </div>
        </div>
    )
  }

}

var ua = window.navigator.userAgent;
var msie = ua.indexOf("MSIE ");
if( msie < 0  || !document.documentMode){
    ReactDOM.render(<NodeSubscribeForm />, document.getElementById("node-subscribe-form"));
}
